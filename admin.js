// Admin Panel JavaScript

// Contact Information (declared early so syncPreviewToForm can set contactHasUnsavedChanges safely)
let contactAutoSaveTimer = null;
let contactHasUnsavedChanges = false;
/** Deduplicate concurrent get_content for Contact (prefetch + opening tab) */
let contactDataLoadInFlight = null;
const DEFAULT_CONTACT_PHONE = '+1 (555) 123‑4567';
const DEFAULT_CONTACT_EMAIL = 'hello@backtobase.example';
const DEFAULT_CONTACT_ADDRESS = 'British Columbia, Canada';

// --- Shared: api.php save responses (JSON { success, error? }) for all admin sections ---
function parseJsonFromText(rawText) {
  if (rawText == null || !String(rawText).trim()) {
    return null;
  }
  try {
    return JSON.parse(String(rawText));
  } catch (e) {
    return null;
  }
}

function isApiSaveSuccess(result) {
  return result && result.success === true;
}

/** @type {ReturnType<typeof setTimeout> | null} */
let adminGlobalSaveBarAutoHideTimer = null;
const ADMIN_GLOBAL_SAVED_AUTO_HIDE_MS = 2000;
const ADMIN_GLOBAL_ERROR_AUTO_HIDE_MS = 8000;

const ADMIN_GLOBAL_SECTION_LABELS = {
  homepage: 'Home',
  explore: 'Explore',
  'room-basement': 'Basement',
  'room-ground-queen': 'Ground (Queen)',
  'room-ground-twin': 'Ground (Twin)',
  'room-second': 'Second floor',
  retreat: 'Retreat',
  special: 'Specials',
  about: 'About',
  contact: 'Contact',
  'homepage-rooms': 'Homepage — rooms',
  floorplan: 'Floor plan',
  'guest-reviews': 'Guest reviews',
  wellness: 'Wellness',
  massage: 'Massage'
};

function getAdminGlobalSectionLabel(prefix) {
  if (ADMIN_GLOBAL_SECTION_LABELS[prefix] != null) {
    return ADMIN_GLOBAL_SECTION_LABELS[prefix];
  }
  return String(prefix)
    .split('-')
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1).toLowerCase())
    .join(' ');
}

function clearAdminGlobalSaveBarAutoHide() {
  if (adminGlobalSaveBarAutoHideTimer) {
    clearTimeout(adminGlobalSaveBarAutoHideTimer);
    adminGlobalSaveBarAutoHideTimer = null;
  }
}

function hideAdminGlobalSaveBar() {
  const el = document.getElementById('admin-save-banner');
  if (el) {
    el.classList.remove('is-visible');
    el.setAttribute('hidden', '');
    el.removeAttribute('data-state');
    el.removeAttribute('title');
  }
  clearAdminGlobalSaveBarAutoHide();
  document.body.classList.remove('admin-global-save-banner-padded');
}

function localizeLegacyRussianAdminLabels() {
  const map = new Map([
    ['Изменить', 'Edit'],
    ['Заменить', 'Replace']
  ]);
  document.querySelectorAll('.image-edit-btn').forEach((btn) => {
    const current = (btn.textContent || '').trim();
    if (map.has(current)) {
      btn.textContent = map.get(current);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', localizeLegacyRussianAdminLabels);
} else {
  localizeLegacyRussianAdminLabels();
}

/**
 * Global top banner for save operations (all CMS sections that use {prefix}-save-status-*).
 * @param {string} prefix
 * @param {'saving'|'saved'|'error'} status
 * @param {string} [detail] - for error: message (full string in title if long)
 */
function updateAdminGlobalSaveBar(prefix, status, detail) {
  const el = document.getElementById('admin-save-banner');
  const sectionEl = document.getElementById('admin-save-banner-section');
  const iconEl = document.getElementById('admin-save-banner-icon');
  const msgEl = document.getElementById('admin-save-banner-message');
  if (!el || !sectionEl || !iconEl || !msgEl) {
    return;
  }
  const label = getAdminGlobalSectionLabel(prefix);
  clearAdminGlobalSaveBarAutoHide();
  if (status === 'saving' || status === 'saved' || status === 'error') {
    document.body.classList.add('admin-global-save-banner-padded');
  }
  el.removeAttribute('hidden');
  el.classList.add('is-visible');
  el.removeAttribute('title');
  if (label) {
    sectionEl.textContent = label + ' —';
  } else {
    sectionEl.textContent = '';
  }
  switch (status) {
    case 'saving': {
      el.setAttribute('data-state', 'saving');
      iconEl.textContent = '⏳';
      msgEl.textContent = 'Saving…';
      break;
    }
    case 'saved': {
      el.setAttribute('data-state', 'saved');
      iconEl.textContent = '✓';
      msgEl.textContent = 'Saved';
      adminGlobalSaveBarAutoHideTimer = setTimeout(() => {
        hideAdminGlobalSaveBar();
      }, ADMIN_GLOBAL_SAVED_AUTO_HIDE_MS);
      break;
    }
    case 'error': {
      const d = (detail && String(detail).trim()) ? String(detail).trim() : 'Error saving';
      const short = d.length > 120 ? d.slice(0, 120) + '…' : d;
      el.setAttribute('data-state', 'error');
      iconEl.textContent = '✗';
      msgEl.textContent = short;
      if (d.length > 120) {
        el.setAttribute('title', d);
      }
      adminGlobalSaveBarAutoHideTimer = setTimeout(() => {
        hideAdminGlobalSaveBar();
      }, ADMIN_GLOBAL_ERROR_AUTO_HIDE_MS);
      break;
    }
    default:
      hideAdminGlobalSaveBar();
  }
}

/**
 * Retreat / Russian save strings — same semantics as #retreat-save-status-* in HTML.
 * @param {string} text
 * @param {string} [icon]
 */
function updateAdminGlobalRetreatSaveBar(text, icon) {
  const raw = (text == null) ? '' : String(text);
  const trimmed = raw.trim();
  if (!trimmed) {
    hideAdminGlobalSaveBar();
    return;
  }
  const el = document.getElementById('admin-save-banner');
  const sectionEl = document.getElementById('admin-save-banner-section');
  const iconEl = document.getElementById('admin-save-banner-icon');
  const msgEl = document.getElementById('admin-save-banner-message');
  if (!el || !sectionEl || !iconEl || !msgEl) {
    return;
  }
  clearAdminGlobalSaveBarAutoHide();
  document.body.classList.add('admin-global-save-banner-padded');
  el.removeAttribute('hidden');
  el.classList.add('is-visible');
  el.removeAttribute('title');
  sectionEl.textContent = 'Retreat —';
  msgEl.textContent = raw;
  const ic = (icon == null) ? '' : String(icon);
  iconEl.textContent = ic;
  if (trimmed === 'Saving...') {
    el.setAttribute('data-state', 'saving');
    if (!ic) {
      iconEl.textContent = '⏳';
    }
    return;
  }
  if (trimmed === 'Saved') {
    el.setAttribute('data-state', 'saved');
    if (!ic) {
      iconEl.textContent = '✓';
    }
    adminGlobalSaveBarAutoHideTimer = setTimeout(hideAdminGlobalSaveBar, ADMIN_GLOBAL_SAVED_AUTO_HIDE_MS);
    return;
  }
  const isRetry = /Retry/i.test(trimmed) && /Error/i.test(trimmed);
  if (isRetry) {
    el.setAttribute('data-state', 'saving');
    if (!ic) {
      iconEl.textContent = '⚠️';
    }
    return;
  }
  const isError =
    /Error|error/i.test(trimmed) || /^Not\s+saved/i.test(trimmed) || /^not\s+saved/i.test(trimmed) || ic === '❌';
  if (isError) {
    el.setAttribute('data-state', 'error');
    if (!ic) {
      iconEl.textContent = '✗';
    }
    adminGlobalSaveBarAutoHideTimer = setTimeout(hideAdminGlobalSaveBar, ADMIN_GLOBAL_ERROR_AUTO_HIDE_MS);
  } else {
    el.setAttribute('data-state', 'saving');
  }
}

function getApiSaveErrorMessage(result, response, defaultMsg) {
  if (result && result.error != null && String(result.error).trim() !== '') {
    return String(result.error);
  }
  if (result && result.message != null && String(result.message).trim() !== '') {
    return String(result.message);
  }
  if (response && !response.ok) {
    return (response.statusText && String(response.statusText).trim()) ? String(response.statusText) : 'HTTP ' + response.status;
  }
  if (!result) {
    return defaultMsg || 'Invalid or empty server response';
  }
  if (result.success === false) {
    return (result.error || result.message || defaultMsg || 'Save failed');
  }
  return defaultMsg || 'Save did not complete';
}

/**
 * @param {string} prefix - ids: {prefix}-save-status-text, {prefix}-save-status-icon
 * @param {'saving'|'saved'|'error'} status
 * @param {string} [detail] - error (truncated in label; full in title)
 */
function updateAdminSectionSaveStatus(prefix, status, detail) {
  const statusText = document.getElementById(prefix + '-save-status-text');
  const statusIcon = document.getElementById(prefix + '-save-status-icon');
  if (statusText) {
    statusText.removeAttribute('title');
  }
  if (statusText && statusIcon) {
    switch (status) {
      case 'saving':
        statusText.textContent = 'Saving...';
        statusIcon.textContent = '⏳';
        statusIcon.style.color = '#6b7280';
        break;
      case 'saved':
        statusText.textContent = 'Saved';
        statusIcon.textContent = '✓';
        statusIcon.style.color = '#10b981';
        setTimeout(() => {
          statusText.textContent = '';
          statusIcon.textContent = '';
        }, 2000);
        break;
      case 'error': {
        const d = (detail && String(detail).trim()) ? String(detail).trim() : 'Error saving';
        const short = d.length > 100 ? d.slice(0, 100) + '…' : d;
        statusText.textContent = short;
        if (d.length > 100) {
          statusText.setAttribute('title', d);
        }
        statusIcon.textContent = '✗';
        statusIcon.style.color = '#ef4444';
        setTimeout(() => {
          statusText.textContent = '';
          statusIcon.textContent = '';
          statusText.removeAttribute('title');
        }, 8000);
        break;
      }
    }
  }
  updateAdminGlobalSaveBar(prefix, status, detail);
}

/**
 * POST FormData to api.php and set section status. Use for action save_content and save_floorplan, etc.
 * @returns {Promise<{ ok: boolean, result: object | null, error?: string }>}
 */
async function postApiFormDataAndUpdateStatus(prefix, formData) {
  const response = await fetch('api.php', { method: 'POST', body: formData });
  const rawText = await response.text();
  const result = parseJsonFromText(rawText);
  if (response.ok && isApiSaveSuccess(result)) {
    updateAdminSectionSaveStatus(prefix, 'saved');
    return { ok: true, result };
  }
  const err = getApiSaveErrorMessage(result, response, 'Save failed');
  console.error('postApiFormDataAndUpdateStatus failed:', prefix, err, result);
  updateAdminSectionSaveStatus(prefix, 'error', err);
  return { ok: false, result, error: err };
}

/**
 * Check save_content response (already read as text). Optionally show section error status.
 * @param {string} [statusPrefix] - e.g. 'explore', 'retreat' for updateAdminSectionSaveStatus on failure
 * @returns {boolean} true if DB save succeeded per API
 */
function recordSaveContentResponse(response, rawText, statusPrefix) {
  const result = parseJsonFromText(rawText);
  if (response.ok && isApiSaveSuccess(result)) {
    return true;
  }
  const err = getApiSaveErrorMessage(result, response, 'Save failed');
  console.error('save_content response not successful:', err);
  if (statusPrefix) {
    updateAdminSectionSaveStatus(statusPrefix, 'error', err);
  }
  return false;
}

// Admin authentication
const ADMIN_CREDENTIALS = {
  username: 'admin',
  password: 'backtobase2024'
};

// Check if user is authenticated
function checkAdminAuth() {
  const isAuthenticated = localStorage.getItem('btb_admin_auth') === 'true';
  if (!isAuthenticated && !window.location.pathname.includes('admin-login')) {
    window.location.href = 'admin-login.html';
  }
}

// Login form handler
function initAdminLogin() {
  const loginForm = document.getElementById('admin-login-form');
  if (!loginForm) return;

  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const username = document.getElementById('admin-username').value;
    const password = document.getElementById('admin-password').value;
    const errorDiv = document.getElementById('admin-login-error');
    
    if (username === ADMIN_CREDENTIALS.username && password === ADMIN_CREDENTIALS.password) {
      localStorage.setItem('btb_admin_auth', 'true');
      window.location.href = 'admin.html';
    } else {
      errorDiv.textContent = 'Invalid username or password';
      errorDiv.style.display = 'block';
    }
  });
}

// Logout function
function adminLogout() {
  localStorage.removeItem('btb_admin_auth');
  window.location.href = 'admin-login.html';
}

// Apply styles to Flatpickr navigation arrows
function applyFlatpickrArrowStyles(instance) {
  if (!instance || !instance.calendarContainer) return;
  
  const lightColor = '#e9eef3'; // var(--text)
  const whiteColor = '#ffffff';
  
  // Find all navigation arrows - исключаем стрелки вверх-вниз для года, так как используется выпадающий список
  const arrows = instance.calendarContainer.querySelectorAll(
    '.flatpickr-prev-month, .flatpickr-next-month, .flatpickr-yearDropdown-prev, .flatpickr-yearDropdown-next, .flatpickr-yearDropdown-years button, .flatpickr-yearDropdown-years [class*="prev"], .flatpickr-yearDropdown-years [class*="next"]'
  );
  
  arrows.forEach(arrow => {
    // Apply color to the element itself with !important via style
    arrow.style.setProperty('color', lightColor, 'important');
    arrow.style.setProperty('fill', lightColor, 'important');
    arrow.style.setProperty('stroke', lightColor, 'important');
    
    // Find and style all SVG elements
    const svgs = arrow.querySelectorAll('svg');
    svgs.forEach(svg => {
      svg.style.setProperty('fill', lightColor, 'important');
      svg.style.setProperty('stroke', lightColor, 'important');
      svg.style.setProperty('color', lightColor, 'important');
      svg.setAttribute('fill', lightColor);
      svg.setAttribute('stroke', lightColor);
    });
    
    // Find and style all path elements
    const paths = arrow.querySelectorAll('path');
    paths.forEach(path => {
      path.style.setProperty('fill', lightColor, 'important');
      path.style.setProperty('stroke', lightColor, 'important');
      path.setAttribute('fill', lightColor);
      path.setAttribute('stroke', lightColor);
    });
    
    // Add hover event listener (only if not already added)
    if (!arrow.dataset.arrowStyled) {
      arrow.dataset.arrowStyled = 'true';
      
      arrow.addEventListener('mouseenter', function(e) {
        e.stopPropagation();
        this.style.setProperty('color', whiteColor, 'important');
        this.style.setProperty('fill', whiteColor, 'important');
        this.style.setProperty('stroke', whiteColor, 'important');
        this.querySelectorAll('svg').forEach(svg => {
          svg.style.setProperty('fill', whiteColor, 'important');
          svg.style.setProperty('stroke', whiteColor, 'important');
          svg.style.setProperty('color', whiteColor, 'important');
          svg.setAttribute('fill', whiteColor);
          svg.setAttribute('stroke', whiteColor);
        });
        this.querySelectorAll('path').forEach(path => {
          path.style.setProperty('fill', whiteColor, 'important');
          path.style.setProperty('stroke', whiteColor, 'important');
          path.setAttribute('fill', whiteColor);
          path.setAttribute('stroke', whiteColor);
        });
      });
      
      arrow.addEventListener('mouseleave', function(e) {
        e.stopPropagation();
        this.style.setProperty('color', lightColor, 'important');
        this.style.setProperty('fill', lightColor, 'important');
        this.style.setProperty('stroke', lightColor, 'important');
        this.querySelectorAll('svg').forEach(svg => {
          svg.style.setProperty('fill', lightColor, 'important');
          svg.style.setProperty('stroke', lightColor, 'important');
          svg.style.setProperty('color', lightColor, 'important');
          svg.setAttribute('fill', lightColor);
          svg.setAttribute('stroke', lightColor);
        });
        this.querySelectorAll('path').forEach(path => {
          path.style.setProperty('fill', lightColor, 'important');
          path.style.setProperty('stroke', lightColor, 'important');
          path.setAttribute('fill', lightColor);
          path.setAttribute('stroke', lightColor);
        });
      });
    }
  });
}

// Two-level navigation system
let currentPrimary = 'bookings'; // Default primary section
const contentEditorRegistry = {};

// Switch primary section (Bookings Management, Content Management, Account Management)
function switchPrimarySection(primaryName) {
  currentPrimary = primaryName;
  
  // Remove active class from all primary tabs
  document.querySelectorAll('.admin-nav-tab-primary').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Add active class to clicked primary tab
  const activePrimaryTab = document.querySelector(`[data-primary="${primaryName}"]`);
  if (activePrimaryTab) {
    activePrimaryTab.classList.add('active');
  }
  
  // Hide all secondary tab groups
  document.querySelectorAll('.admin-nav-tabs-secondary').forEach(group => {
    group.classList.add('hidden');
  });
  
  // Show secondary tabs for selected primary section
  const secondaryTabs = document.querySelector(`.admin-nav-tabs-secondary[data-primary="${primaryName}"]`);
  if (secondaryTabs) {
    secondaryTabs.classList.remove('hidden');
  }
  
  // Activate first secondary tab (Dashboard) for the selected primary section
  if (secondaryTabs) {
    const firstSecondaryTab = secondaryTabs.querySelector('.admin-nav-tab-secondary');
    if (firstSecondaryTab) {
      const sectionName = firstSecondaryTab.getAttribute('data-section');
      showSection(sectionName);
    }
  }
  if (primaryName === 'content' && typeof loadContactData === 'function') {
    setTimeout(function () { loadContactData(); }, 0);
  }
}

// Navigation between sections (secondary level)
function showSection(sectionName) {
  // Check for unsaved changes in retreat section before switching
  // Only check if variables and functions are defined (retreat section was loaded)
  if (typeof retreatHasUnsavedChanges !== 'undefined' && 
      typeof retreatIsSaving !== 'undefined' && 
      typeof retreatAutoSaveTimer !== 'undefined' &&
      typeof autoSaveRetreatContent === 'function' &&
      retreatHasUnsavedChanges && 
      !retreatIsSaving && 
      sectionName !== 'retreat-workshop') {
    // Force save before switching
    if (retreatAutoSaveTimer) {
      clearTimeout(retreatAutoSaveTimer);
      retreatAutoSaveTimer = null;
    }
    try {
      autoSaveRetreatContent().then(() => {
        // Continue switching after save completes
        performSectionSwitch(sectionName);
      }).catch(() => {
        // Even if save fails, allow switching (user can come back)
        performSectionSwitch(sectionName);
      });
    } catch (error) {
      console.error('Error in auto-save before section switch:', error);
      // If auto-save fails, still allow switching
      performSectionSwitch(sectionName);
    }
  } else {
    performSectionSwitch(sectionName);
  }
}

function performSectionSwitch(sectionName) {
  // Reset retreat auto-save state when switching away
  // Only reset if variables and functions are defined (retreat section was loaded)
  if (typeof retreatHasUnsavedChanges !== 'undefined' && 
      typeof updateRetreatSaveStatus === 'function' &&
      typeof retreatAutoSaveTimer !== 'undefined' &&
      sectionName !== 'retreat-workshop') {
    try {
      retreatHasUnsavedChanges = false;
      updateRetreatSaveStatus('', '');
      if (retreatAutoSaveTimer) {
        clearTimeout(retreatAutoSaveTimer);
        retreatAutoSaveTimer = null;
      }
    } catch (error) {
      console.error('Error resetting retreat auto-save state:', error);
      // Continue with section switch even if reset fails
    }
  }
  
  // Hide all sections
  document.querySelectorAll('.admin-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Remove active class from all secondary nav tabs
  document.querySelectorAll('.admin-nav-tab-secondary').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected section
  const targetSection = document.getElementById(`${sectionName}-section`);
  if (targetSection) {
    targetSection.style.display = 'block';
    console.log('Showing section:', sectionName, 'targetSection found:', targetSection.id);
  } else {
    console.warn('Section not found:', `${sectionName}-section`);
    // Try alternative naming
    const altSection = document.querySelector(`[id*="${sectionName}"]`);
    if (altSection) {
      console.log('Found alternative section:', altSection.id);
      altSection.style.display = 'block';
    }
  }
  
  // Add active class to clicked secondary tab
  const activeTab = document.querySelector(`[data-section="${sectionName}"]`);
  if (activeTab) {
    activeTab.classList.add('active');
  } else {
    console.warn('Tab not found for section:', sectionName);
  }
  
  // Load section data
  console.log('showSection: Loading data for section:', sectionName);
  loadSectionData(sectionName);
}

// Load data for specific section
function registerContentEditor(sectionName, initializer) {
  if (!sectionName || typeof initializer !== 'function') return;
  contentEditorRegistry[sectionName] = initializer;
}

function initContentEditor(sectionName) {
  console.log('initContentEditor called for:', sectionName);
  if (typeof contentEditorRegistry[sectionName] === 'function') {
    console.log('Calling registered initializer for:', sectionName);
    contentEditorRegistry[sectionName]();
  } else {
    console.warn('No initializer registered for:', sectionName);
  }
}

function loadSectionData(sectionName) {
  switch(sectionName) {
    case 'homepage':
      initContentEditor('homepage');
      break;
    case 'homepage-rooms':
      initContentEditor('homepage-rooms');
      break;
    case 'floorplan':
      console.log('loadSectionData: Initializing floorplan editor');
      initContentEditor('floorplan');
      break;
    case 'guest-reviews':
      initContentEditor('guest-reviews');
      break;
    case 'room-basement':
      initContentEditor('room-basement');
      break;
    case 'room-ground-queen':
      initContentEditor('room-ground-queen');
      break;
    case 'room-ground-twin':
      initContentEditor('room-ground-twin');
      break;
    case 'room-second':
      initContentEditor('room-second');
      break;
    case 'massage':
      initContentEditor('massage');
      break;
    case 'retreat-workshop':
      initContentEditor('retreat-workshop');
      break;
    case 'special':
      initContentEditor('special');
      break;
    case 'explore':
      initContentEditor('explore');
      break;
    case 'about':
      initContentEditor('about');
      break;
    case 'contact':
      initContentEditor('contact');
      break;
    case 'wellness-experiences':
      initContentEditor('wellness-experiences');
      break;
    case 'rooms':
      loadRoomsData();
      break;
    case 'yoga':
      loadYogaData();
      break;
    case 'images':
      loadImagesData();
      break;
    case 'bookings':
      loadBookingsData();
      initBookingsFilters();
      break;
    case 'calendar':
      loadCalendarData();
      loadBlockedDates();
      initCalendarBlocking();
      break;
    case 'accounts':
      loadAccountsData();
      initAccountsFilters();
      break;
    case 'bookings-dashboard':
      initDashboardFilters('bookings');
      updateBookingsDashboardStats();
      break;
    case 'accounts-dashboard':
      initDashboardFilters('accounts');
      updateAccountsDashboardStats();
      break;
    case 'dashboard':
      // Legacy support
      updateDashboardStats();
      break;
  }
}

// Homepage management
async function loadHomepageData() {
  console.log('Loading homepage data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Update hidden fields and preview
        const descriptionField = document.getElementById('homepage-main-description');
        const subtitleField = document.getElementById('homepage-main-subtitle');
        const descriptionPreview = document.getElementById('preview-homepage-main-description');
        const subtitlePreview = document.getElementById('preview-homepage-main-subtitle');
        
        if (descriptionField) {
          const description = data.homepageDescription || '';
          descriptionField.value = description;
          // Use textContent - white-space: pre-wrap handles line breaks
          if (descriptionPreview) {
            descriptionPreview.textContent = description;
          }
        }
        
        if (subtitleField) {
          const subtitle = data.homepageSubtitle || '';
          subtitleField.value = subtitle;
          // Use textContent - white-space: pre-wrap handles line breaks
          if (subtitlePreview) {
            subtitlePreview.textContent = subtitle;
          }
        }
        
        // Load hero images
        const heroImageUrl = data.heroImageUrl || '';
        const hero2ImageUrl = data.hero2ImageUrl || '';
        
        // Update hidden image URL fields
        const heroImageUrlField = document.getElementById('homepage-hero-image-url');
        const hero2ImageUrlField = document.getElementById('homepage-hero2-image-url');
        if (heroImageUrlField) heroImageUrlField.value = heroImageUrl;
        if (hero2ImageUrlField) hero2ImageUrlField.value = hero2ImageUrl;
        
        // Update preview images
        const heroImg = document.getElementById('preview-homepage-hero-img');
        const hero2Img = document.getElementById('preview-homepage-hero2-img');
        
        if (heroImg && heroImageUrl) {
          heroImg.src = heroImageUrl + '?v=' + Date.now();
          heroImg.style.display = 'block';
          const placeholder = heroImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        if (hero2Img && hero2ImageUrl) {
          hero2Img.src = hero2ImageUrl + '?v=' + Date.now();
          hero2Img.style.display = 'block';
          const placeholder = hero2Img.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        console.log('Homepage content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load homepage data:', error);
  }
}

// Load homepage images data
async function loadHomepageImagesData() {
  console.log('Loading homepage images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.heroImageUrl || '';
        const hero2ImageUrl = result.data.hero2ImageUrl || '';
        
        // Update preview images directly
        const heroImg = document.getElementById('preview-homepage-hero-img');
        const hero2Img = document.getElementById('preview-homepage-hero2-img');
        
        if (heroImg && heroImageUrl) {
          heroImg.src = heroImageUrl + '?v=' + Date.now();
          heroImg.style.display = 'block';
          const placeholder = heroImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        if (hero2Img && hero2ImageUrl) {
          hero2Img.src = hero2ImageUrl + '?v=' + Date.now();
          hero2Img.style.display = 'block';
          const placeholder = hero2Img.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        // Update hidden fields
        const heroImageUrlField = document.getElementById('homepage-hero-image-url');
        const hero2ImageUrlField = document.getElementById('homepage-hero2-image-url');
        if (heroImageUrlField) heroImageUrlField.value = heroImageUrl;
        if (hero2ImageUrlField) hero2ImageUrlField.value = hero2ImageUrl;
      }
    }
  } catch (error) {
    console.log('Failed to load homepage images data:', error);
  }
}

// Homepage auto-save functionality
let homepageAutoSaveTimer = null;
let homepageHasUnsavedChanges = false;

function scheduleHomepageAutoSave() {
  if (homepageAutoSaveTimer) {
    clearTimeout(homepageAutoSaveTimer);
  }
  
  homepageAutoSaveTimer = setTimeout(() => {
    if (homepageHasUnsavedChanges) {
      saveHomepageContent();
      homepageHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateHomepageSaveStatus('saving');
}

// Make scheduleHomepageAutoSave globally accessible
window.scheduleHomepageAutoSave = scheduleHomepageAutoSave;

async function saveHomepageContent() {
  updateHomepageSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('homepage_description', document.getElementById('homepage-main-description')?.value || '');
    formData.append('homepage_subtitle', document.getElementById('homepage-main-subtitle')?.value || '');
    
    // Get image URLs from hidden fields
    formData.append('hero_image_url', document.getElementById('homepage-hero-image-url')?.value || '');
    formData.append('hero2_image_url', document.getElementById('homepage-hero2-image-url')?.value || '');
    
    const { ok } = await postApiFormDataAndUpdateStatus('homepage', formData);
    if (ok) {
      setTimeout(() => {
        loadHomepageData();
      }, 500);
    }
  } catch (error) {
    console.error('Error saving homepage content:', error);
    updateHomepageSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateHomepageSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('homepage', status, detail);
}

function initHomepageAutoSave() {
  // syncPreviewToForm already handles triggering auto-save for homepage fields
  // This function is here for consistency with other pages
  console.log('Homepage auto-save initialized');
}

// Floor Plan auto-save functionality
let floorplanAutoSaveTimer = null;
let floorplanHasUnsavedChanges = false;

function scheduleFloorplanAutoSave() {
  console.log('scheduleFloorplanAutoSave called, floorplanHasUnsavedChanges:', floorplanHasUnsavedChanges);
  if (floorplanAutoSaveTimer) {
    clearTimeout(floorplanAutoSaveTimer);
  }
  
  floorplanAutoSaveTimer = setTimeout(() => {
    console.log('Floorplan auto-save timer fired, floorplanHasUnsavedChanges:', floorplanHasUnsavedChanges);
    if (floorplanHasUnsavedChanges) {
      console.log('Calling saveFloorplanContent');
      saveFloorplanContent();
      floorplanHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateFloorplanSaveStatus('saving');
}

// Make scheduleFloorplanAutoSave globally accessible
window.scheduleFloorplanAutoSave = scheduleFloorplanAutoSave;

async function saveFloorplanContent() {
  console.log('saveFloorplanContent called');
  updateFloorplanSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_floorplan');
    
    // Get all field values - API expects camelCase
    const floorplanTitle = document.getElementById('floorplan-title')?.value || '';
    const floorplanSubtitle = document.getElementById('floorplan-subtitle')?.value || '';
    const basementSubtitle = document.getElementById('basement-subtitle')?.value || '';
    const basementDescription = document.getElementById('basement-description')?.value || '';
    const groundSubtitle = document.getElementById('ground-subtitle')?.value || '';
    const groundDescription = document.getElementById('ground-description')?.value || '';
    const loftSubtitle = document.getElementById('loft-subtitle')?.value || '';
    const loftDescription = document.getElementById('loft-description')?.value || '';
    
    // Get image URLs from path elements or localStorage
    const stored = localStorage.getItem('btb_floorplan_settings');
    const storedJson = stored ? JSON.parse(stored) : {};
    const basementPathEl = document.getElementById('basement-image-path');
    const groundPathEl = document.getElementById('ground-image-path');
    const loftPathEl = document.getElementById('loft-image-path');
    const basementImageUrl = (basementPathEl && basementPathEl.textContent) || storedJson.basement_image_url || '';
    const groundQueenImage = (groundPathEl && groundPathEl.textContent) || storedJson.ground_image_url || storedJson.ground_queen_image || '';
    const loftImageUrl = (loftPathEl && loftPathEl.textContent) || storedJson.loft_image_url || '';
    
    console.log('Saving floorplan data:', {
      floorplanTitle: floorplanTitle.substring(0, 50),
      floorplanSubtitle: floorplanSubtitle.substring(0, 50),
      basementSubtitle: basementSubtitle.substring(0, 50),
      basementDescription: basementDescription.substring(0, 50),
      groundSubtitle: groundSubtitle.substring(0, 50),
      groundDescription: groundDescription.substring(0, 50),
      loftSubtitle: loftSubtitle.substring(0, 50),
      loftDescription: loftDescription.substring(0, 50)
    });
    
    formData.append('floorplanTitle', floorplanTitle);
    formData.append('floorplanSubtitle', floorplanSubtitle);
    formData.append('basementSubtitle', basementSubtitle);
    formData.append('basementDescription', basementDescription);
    formData.append('basementImageUrl', basementImageUrl);
    formData.append('groundSubtitle', groundSubtitle);
    formData.append('groundDescription', groundDescription);
    formData.append('groundQueenImage', groundQueenImage);
    formData.append('groundTwinImage', '');
    formData.append('loftSubtitle', loftSubtitle);
    formData.append('loftDescription', loftDescription);
    formData.append('loftImageUrl', loftImageUrl);
    
    // Add galleries - IMPORTANT: always include galleries when saving
    formData.append('basementGallery', document.getElementById('basement-gallery')?.value || '[]');
    formData.append('groundGallery', document.getElementById('ground-gallery')?.value || '[]');
    formData.append('loftGallery', document.getElementById('loft-gallery')?.value || '[]');
    
    console.log('saveFloorplanContent: Saving galleries:', {
      basement: document.getElementById('basement-gallery')?.value || '[]',
      ground: document.getElementById('ground-gallery')?.value || '[]',
      loft: document.getElementById('loft-gallery')?.value || '[]'
    });
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Floorplan save response status:', response.status);
    const rawText = await response.text();
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      console.error('Floorplan save failed: Server returned non-JSON response:', rawText.substring(0, 200));
      updateFloorplanSaveStatus('error', 'Server did not return JSON. Run add_floorplan_title_fields.php?');
      alert('Save error: Server returned an invalid response format. Required database columns may be missing. Please run add_floorplan_title_fields.php.');
      return;
    }
    const result = parseJsonFromText(rawText);
    console.log('Floorplan save response:', result);
    if (response.ok && isApiSaveSuccess(result)) {
      updateFloorplanSaveStatus('saved');
      setTimeout(() => {
        loadFloorplanData();
      }, 500);
    } else {
      const errorMsg = getApiSaveErrorMessage(result, response, 'Save failed');
      console.error('Floorplan save failed:', errorMsg);
      updateFloorplanSaveStatus('error', errorMsg);
      if (errorMsg.includes('columns missing') || errorMsg.includes('floorplan_title') || errorMsg.includes('floorplan_subtitle')) {
        alert('Error: Required database columns are missing. Please run add_floorplan_title_fields.php on the server.');
      }
    }
  } catch (error) {
    console.error('Error saving floorplan content:', error);
    updateFloorplanSaveStatus('error', (error && error.message) || 'Save failed');
    if (error.message && error.message.includes('JSON')) {
      alert('Save error: Server returned an invalid response format. Required database columns may be missing. Please run add_floorplan_title_fields.php.');
    }
  }
}

function updateFloorplanSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('floorplan', status, detail);
}

function initFloorplanAutoSave() {
  // syncPreviewToForm already handles triggering auto-save for floorplan fields
  // This function is here for consistency with other pages
  console.log('Floor plan auto-save initialized');
}

// Initialize homepage image upload
function initHomepageImageUpload() {
  // Hero image upload
  const heroUploadBtn = document.getElementById('hero-upload-btn');
  const heroUploadInput = document.getElementById('hero-image-upload');
  
  if (heroUploadBtn && heroUploadInput) {
    heroUploadBtn.addEventListener('click', () => {
      heroUploadInput.click();
    });
    
    heroUploadInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'homepage-hero', 
          document.getElementById('preview-homepage-hero-img'),
          null,
          {
            localStorageKey: 'btb_content',
            fieldNameMapper: () => 'hero_image_url',
            reloadFunction: loadHomepageImagesData,
            imageNameMapper: () => 'Hero'
          }
        );
      }
    });
  }
  
  // Hero2 image upload
  const hero2UploadBtn = document.getElementById('hero2-upload-btn');
  const hero2UploadInput = document.getElementById('hero2-image-upload');
  
  if (hero2UploadBtn && hero2UploadInput) {
    hero2UploadBtn.addEventListener('click', () => {
      hero2UploadInput.click();
    });
    
    hero2UploadInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'homepage-hero2', 
          document.getElementById('preview-homepage-hero2-img'),
          null,
          {
            localStorageKey: 'btb_content',
            fieldNameMapper: () => 'hero2_image_url',
            reloadFunction: loadHomepageImagesData,
            imageNameMapper: () => 'Hero2'
          }
        );
      }
    });
  }
  
  // Also handle new upload inputs
  const homepageHeroUpload = document.getElementById('homepage-hero-upload');
  const homepageHero2Upload = document.getElementById('homepage-hero2-upload');
  
  if (homepageHeroUpload) {
    homepageHeroUpload.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'homepage-hero', 
          document.getElementById('preview-homepage-hero-img'),
          null,
          {
            localStorageKey: 'btb_content',
            fieldNameMapper: () => 'hero_image_url',
            reloadFunction: loadHomepageImagesData,
            imageNameMapper: () => 'Hero',
            onSuccess: (imageUrl) => {
              const heroImageUrlField = document.getElementById('homepage-hero-image-url');
              if (heroImageUrlField) {
                heroImageUrlField.value = imageUrl;
                homepageHasUnsavedChanges = true;
                scheduleHomepageAutoSave();
              }
            }
          }
        );
      }
    });
  }
  
  if (homepageHero2Upload) {
    homepageHero2Upload.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadImage(file, 'homepage-hero2', 
          document.getElementById('preview-homepage-hero2-img'),
          null,
          {
            localStorageKey: 'btb_content',
            fieldNameMapper: () => 'hero2_image_url',
            reloadFunction: loadHomepageImagesData,
            imageNameMapper: () => 'Hero2',
            onSuccess: (imageUrl) => {
              const hero2ImageUrlField = document.getElementById('homepage-hero2-image-url');
              if (hero2ImageUrlField) {
                hero2ImageUrlField.value = imageUrl;
                homepageHasUnsavedChanges = true;
                scheduleHomepageAutoSave();
              }
            }
          }
        );
      }
    });
  }
}

// Floor Plan management
async function loadFloorplanData() {
  // Load existing floorplan content
  try {
    console.log('Loading floorplan data...');
    const formData = new FormData();
    formData.append('action', 'get_floorplan');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response status:', response.status);
    
    if (response.ok) {
      const result = await response.json();
      console.log('API response:', result);
      
      if (result.success && result.data) {
        console.log('Populating fields with data:', result.data);
        console.log('Full data object:', JSON.stringify(result.data, null, 2));
        const data = result.data;
        
        // Floor Plan Title and Subtitle
        const floorplanTitleField = document.getElementById('floorplan-title');
        const floorplanSubtitleField = document.getElementById('floorplan-subtitle');
        const floorplanTitlePreview = document.getElementById('preview-floorplan-title');
        const floorplanSubtitlePreview = document.getElementById('preview-floorplan-subtitle');
        
        if (floorplanTitleField) {
          const title = data.floorplan_title || 'Common areas';
          floorplanTitleField.value = title;
          if (floorplanTitlePreview) {
            floorplanTitlePreview.textContent = title;
          }
        }
        if (floorplanSubtitleField) {
          const subtitle = data.floorplan_subtitle || 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.';
          floorplanSubtitleField.value = subtitle;
          if (floorplanSubtitlePreview) {
            floorplanSubtitlePreview.textContent = subtitle;
          }
        }
        
        // Basement
        const basementSubtitleField = document.getElementById('basement-subtitle');
        const basementDescField = document.getElementById('basement-description');
        const basementSubtitlePreview = document.getElementById('preview-basement-subtitle');
        const basementDescPreview = document.getElementById('preview-basement-description');
        
        if (basementSubtitleField) {
          const subtitle = data.basement_subtitle || 'Private floor with a separate entrance.';
          basementSubtitleField.value = subtitle;
          if (basementSubtitlePreview) {
            basementSubtitlePreview.textContent = subtitle;
          }
        }
        if (basementDescField) {
          const desc = data.basement_description || 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.';
          console.log('Setting basement-description to:', desc);
          basementDescField.value = desc;
          if (basementDescPreview) {
            basementDescPreview.textContent = desc;
            console.log('Updated preview-basement-description');
          }
        }
        
        // Update basement image
        const basementImage = data.basementImageUrl || data.basement_image_url || '';
        const legacyBasementImg = document.getElementById('preview-basement-floor-img');
        const basementImagePreview = document.getElementById('basement-image-preview');
        const basementImagePath = document.getElementById('basement-image-path');
        if (basementImage) {
          const imageUrl = basementImage + '?v=' + Date.now();
          if (legacyBasementImg) {
            legacyBasementImg.src = imageUrl;
            legacyBasementImg.style.display = 'block';
            const placeholder = legacyBasementImg.nextElementSibling;
            if (placeholder && placeholder.tagName === 'SPAN') {
              placeholder.style.display = 'none';
            }
          }
          if (basementImagePreview) {
            basementImagePreview.innerHTML = `<img src="${imageUrl}" alt="Kitchen" />`;
            basementImagePreview.style.display = 'block';
          }
          if (basementImagePath) {
            basementImagePath.textContent = basementImage;
            basementImagePath.style.display = 'block';
          }
        }
        
        // Ground Floor
        const groundSubtitleField = document.getElementById('ground-subtitle');
        const groundDescField = document.getElementById('ground-description');
        const groundSubtitlePreview = document.getElementById('preview-ground-subtitle');
        const groundDescPreview = document.getElementById('preview-ground-description');
        
        if (groundSubtitleField) {
          const subtitle = data.ground_subtitle || 'Open space with a separate entrance.';
          console.log('Setting ground-subtitle to:', subtitle);
          groundSubtitleField.value = subtitle;
          if (groundSubtitlePreview) {
            groundSubtitlePreview.textContent = subtitle;
            console.log('Updated preview-ground-subtitle');
          }
        }
        if (groundDescField) {
          const desc = data.ground_description || 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.';
          console.log('Setting ground-description to:', desc);
          console.log('ground-description length:', desc.length);
          groundDescField.value = desc;
          if (groundDescPreview) {
            groundDescPreview.textContent = desc;
            console.log('Updated preview-ground-description, length:', groundDescPreview.innerHTML.length);
          }
        }
        
        // Update ground floor image
        const groundImage = data.groundQueenImage || data.ground_image_url || data.ground_queen_image || '';
        const legacyGroundImg = document.getElementById('preview-ground-floor-img');
        const groundImagePreview = document.getElementById('ground-image-preview');
        const groundImagePath = document.getElementById('ground-image-path');
        if (groundImage) {
          const imageUrl = groundImage + '?v=' + Date.now();
          if (legacyGroundImg) {
            legacyGroundImg.src = imageUrl;
            legacyGroundImg.style.display = 'block';
            const placeholder = legacyGroundImg.nextElementSibling;
            if (placeholder && placeholder.tagName === 'SPAN') {
              placeholder.style.display = 'none';
            }
          }
          if (groundImagePreview) {
            groundImagePreview.innerHTML = `<img src="${imageUrl}" alt="Hall" />`;
            groundImagePreview.style.display = 'block';
          }
          if (groundImagePath) {
            groundImagePath.textContent = groundImage;
            groundImagePath.style.display = 'block';
          }
        }
        
        // Loft
        const loftSubtitleField = document.getElementById('loft-subtitle');
        const loftDescField = document.getElementById('loft-description');
        const loftSubtitlePreview = document.getElementById('preview-loft-subtitle');
        const loftDescPreview = document.getElementById('preview-loft-description');
        
        if (loftSubtitleField) {
          const subtitle = data.loft_subtitle || 'Multifunctional spaces & small cinema';
          console.log('Setting loft-subtitle to:', subtitle);
          loftSubtitleField.value = subtitle;
          if (loftSubtitlePreview) {
            loftSubtitlePreview.textContent = subtitle;
            console.log('Updated preview-loft-subtitle');
          }
        }
        if (loftDescField) {
          const desc = data.loft_description || 'A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.';
          console.log('Setting loft-description to:', desc);
          loftDescField.value = desc;
          if (loftDescPreview) {
            loftDescPreview.textContent = desc;
            console.log('Updated preview-loft-description');
          }
        }
        
        // Update loft image
        const loftImage = data.loftImageUrl || data.loft_image_url || '';
        const legacyLoftImg = document.getElementById('preview-loft-floor-img');
        const loftImagePreview = document.getElementById('loft-image-preview');
        const loftImagePath = document.getElementById('loft-image-path');
        if (loftImage) {
          const imageUrl = loftImage + '?v=' + Date.now();
          if (legacyLoftImg) {
            legacyLoftImg.src = imageUrl;
            legacyLoftImg.style.display = 'block';
            const placeholder = legacyLoftImg.nextElementSibling;
            if (placeholder && placeholder.tagName === 'SPAN') {
              placeholder.style.display = 'none';
            }
          }
          if (loftImagePreview) {
            loftImagePreview.innerHTML = `<img src="${imageUrl}" alt="Workshop rooms and home cinema" />`;
            loftImagePreview.style.display = 'block';
          }
          if (loftImagePath) {
            loftImagePath.textContent = loftImage;
            loftImagePath.style.display = 'block';
          }
        }
        
        // Load galleries
        const basementGalleryField = document.getElementById('basement-gallery');
        const groundGalleryField = document.getElementById('ground-gallery');
        const loftGalleryField = document.getElementById('loft-gallery');
        
        if (basementGalleryField) {
          const gallery = data.basement_gallery || '[]';
          basementGalleryField.value = gallery;
          try {
            const galleryArray = JSON.parse(gallery);
            if (typeof updateFloorplanGalleryPreview === 'function') {
              updateFloorplanGalleryPreview('basement', galleryArray);
            }
          } catch (e) {
            console.error('Failed to parse basement gallery:', e);
          }
        }
        
        if (groundGalleryField) {
          const gallery = data.ground_gallery || '[]';
          groundGalleryField.value = gallery;
          try {
            const galleryArray = JSON.parse(gallery);
            if (typeof updateFloorplanGalleryPreview === 'function') {
              updateFloorplanGalleryPreview('ground', galleryArray);
            }
          } catch (e) {
            console.error('Failed to parse ground gallery:', e);
          }
        }
        
        if (loftGalleryField) {
          const gallery = data.loft_gallery || '[]';
          loftGalleryField.value = gallery;
          try {
            const galleryArray = JSON.parse(gallery);
            if (typeof updateFloorplanGalleryPreview === 'function') {
              updateFloorplanGalleryPreview('loft', galleryArray);
            }
          } catch (e) {
            console.error('Failed to parse loft gallery:', e);
          }
        }
        
        // Initialize galleries after loading data (only once)
        if (typeof initFloorplanGalleries === 'function' && !window.floorplanGalleriesInitialized) {
          setTimeout(() => {
            initFloorplanGalleries();
          }, 100);
        }
        
        console.log('Floor plan fields populated successfully');
      } else {
        console.log('No data received or API failed');
      }
    } else {
      console.log('Response not OK:', response.status);
    }
  } catch (error) {
    console.log('Failed to load floorplan data:', error);
  }
}

// Rooms management
function loadRoomsData() {
  const roomsList = document.getElementById('rooms-list');
  if (!roomsList) return;
  
  const rooms = getStoredData('btb_rooms') || getDefaultRooms();
  
  roomsList.innerHTML = rooms.map((room, index) => `
    <div class="admin-room-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${room.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${room.price} CAD/night</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Capacity: ${room.capacity} guests</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${room.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editRoom(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteRoom(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultRooms() {
  return [
    {
      name: 'Loki Suite',
      price: 140,
      capacity: 2,
      type: 'queen',
      description: 'Cozy room next to the home cinema and sauna. Perfect for two.'
    },
    {
      name: 'The Nouk',
      price: 130,
      capacity: 2,
      type: 'queen',
      description: 'Compact, bright room with access to the fireplace lounge.'
    },
    {
      name: 'Vrienden',
      price: 125,
      capacity: 2,
      type: 'twin',
      description: 'Great for friends or colleagues. Close to the kitchen and massage hall.'
    },
    {
      name: 'Kelder',
      price: 210,
      capacity: 2,
      type: 'suite',
      description: 'Separate kitchen and shower, study, and a balcony with lake view.'
    }
  ];
}

// Massage management
function loadMassageData() {
  const massageList = document.getElementById('massage-list');
  if (!massageList) return;
  
  const massages = getStoredData('btb_massage_services') || getDefaultMassageServices();
  
  massageList.innerHTML = massages.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editMassage(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteMassage(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

// Wellness page — per-card price lines (JSON in content_settings: label + price; duration is fixed in booking)
const BTB_MASSAGE_PRICING_DEFAULTS = {
  relaxing: [
    { duration: 60, label: '60 minutes', price: '110 CAD' },
    { duration: 90, label: '90 minutes', price: '160 CAD' }
  ],
  deep: [
    { duration: 60, label: '60 minutes', price: '120 CAD' },
    { duration: 90, label: '90 minutes', price: '170 CAD' }
  ],
  reiki: [
    { duration: 15, label: '15 minutes on the go', price: '25 CAD' },
    { duration: 30, label: '30 minutes as an add-on', price: '50 CAD' }
  ],
  sauna: [{ duration: 60, label: '1 hour', price: '25 CAD' }]
};

function applyMassagePricingUIFromApiString(key, jsonStr) {
  const defaults = BTB_MASSAGE_PRICING_DEFAULTS[key] || [];
  const byDur = Object.create(null);
  let rows = null;
  try {
    rows = jsonStr && String(jsonStr).trim() ? JSON.parse(jsonStr) : null;
  } catch (e) {
    rows = null;
  }
  if (Array.isArray(rows) && rows.length) {
    rows.forEach((r) => {
      if (r && r.duration != null) {
        byDur[Number(r.duration)] = r;
      }
    });
  }
  defaults.forEach((rowDef) => {
    const dur = rowDef.duration;
    const r = byDur[dur] || rowDef;
    const lEl = document.getElementById(`massage-pricing-${key}-${dur}-label`);
    const pEl = document.getElementById(`massage-pricing-${key}-${dur}-price`);
    const label = r.label != null && String(r.label).trim() !== '' ? r.label : rowDef.label;
    const price = r.price != null && String(r.price).trim() !== '' ? r.price : rowDef.price;
    if (lEl) lEl.value = label;
    if (pEl) pEl.value = price;
  });
}

function collectMassagePricingJsonForKey(key) {
  const defaults = BTB_MASSAGE_PRICING_DEFAULTS[key] || [];
  const rows = defaults.map((rowDef) => {
    const dur = rowDef.duration;
    const lEl = document.getElementById(`massage-pricing-${key}-${dur}-label`);
    const pEl = document.getElementById(`massage-pricing-${key}-${dur}-price`);
    return {
      duration: dur,
      label: lEl && lEl.value != null ? String(lEl.value).trim() : '',
      price: pEl && pEl.value != null ? String(pEl.value).trim() : ''
    };
  });
  return JSON.stringify(rows);
}

let massagePricingInputDelegated = false;
function ensureMassagePricingInputDelegation() {
  if (massagePricingInputDelegated) {
    return;
  }
  massagePricingInputDelegated = true;
  document.addEventListener('input', (e) => {
    const t = e.target;
    const id = t && t.id;
    if (!id || !id.startsWith('massage-pricing-')) {
      return;
    }
    if (!t.closest || !t.closest('#massage-section')) {
      return;
    }
    if (typeof massageHasUnsavedChanges !== 'undefined') {
      massageHasUnsavedChanges = true;
    }
    if (typeof window.scheduleMassageAutoSave === 'function') {
      window.scheduleMassageAutoSave();
    }
  });
}

// Load massage page data (text and images)
async function loadMassageData() {
  console.log('Loading massage page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        const heroTitleField = document.getElementById('massage-hero-title');
        const introField = document.getElementById('massage-intro');
        const heroTitlePreview = document.getElementById('preview-massage-hero-title');
        const introPreview = document.getElementById('preview-massage-intro');
        if (heroTitleField) heroTitleField.value = data.massageHeroTitle || '';
        if (introField) introField.value = data.massageIntro || '';
        if (heroTitlePreview) heroTitlePreview.textContent = data.massageHeroTitle || 'Wellness';
        if (introPreview) {
          const introText = data.massageIntro || 'Massage is available as an add-on to your apartment rental or as a stand-alone booking. Whether you want to release tension, restore energy, or simply relax, our experienced therapists are always ready to help.';
          introPreview.textContent = introText;
        }
        
        // Relaxing Massage
        const relaxingTitleField = document.getElementById('massage-relaxing-title');
        const relaxingDescField = document.getElementById('massage-relaxing-description');
        const relaxingTitlePreview = document.getElementById('preview-massage-relaxing-title');
        const relaxingDescPreview = document.getElementById('preview-massage-relaxing-desc');
        if (relaxingTitleField) relaxingTitleField.value = data.massageRelaxingTitle || '';
        if (relaxingDescField) relaxingDescField.value = data.massageRelaxingDescription || '';
        if (relaxingTitlePreview) relaxingTitlePreview.textContent = data.massageRelaxingTitle || 'Relaxing Massage';
        if (relaxingDescPreview) {
          const relaxingText = data.massageRelaxingDescription || 'This gentle massage, perfect for those who want to unwind and restore their energy, uses smooth strokes and calming techniques that relieve stress, improve circulation, and promote relaxation. After the session, you will feel refreshed and relaxed.';
          relaxingDescPreview.textContent = relaxingText;
        }
        
        // Deep Tissue Massage
        const deepTissueTitleField = document.getElementById('massage-deep-tissue-title');
        const deepTissueDescField = document.getElementById('massage-deep-tissue-description');
        const deepTissueTitlePreview = document.getElementById('preview-massage-deep-tissue-title');
        const deepTissueDescPreview = document.getElementById('preview-massage-deep-tissue-desc');
        if (deepTissueTitleField) deepTissueTitleField.value = data.massageDeepTissueTitle || '';
        if (deepTissueDescField) deepTissueDescField.value = data.massageDeepTissueDescription || '';
        if (deepTissueTitlePreview) deepTissueTitlePreview.textContent = data.massageDeepTissueTitle || 'Deep Tissue Massage';
        if (deepTissueDescPreview) {
          const deepText = data.massageDeepTissueDescription || 'For targeted relief of muscle tension and pain, we offer deep tissue massage, designed to address chronic stiffness and discomfort in deeper layers of muscle. It is ideal for those experiencing pain or tightness in specific areas.';
          deepTissueDescPreview.textContent = deepText;
        }
        
        // Reiki Energy Healing
        const reikiTitleField = document.getElementById('massage-reiki-title');
        const reikiDescField = document.getElementById('massage-reiki-description');
        const reikiTitlePreview = document.getElementById('preview-massage-reiki-title');
        const reikiDescPreview = document.getElementById('preview-massage-reiki-desc');
        if (reikiTitleField) reikiTitleField.value = data.massageReikiTitle || '';
        if (reikiDescField) reikiDescField.value = data.massageReikiDescription || '';
        if (reikiTitlePreview) reikiTitlePreview.textContent = data.massageReikiTitle || 'Reiki Energy Healing';
        if (reikiDescPreview) {
          const reikiText = data.massageReikiDescription || 'Experience the gentle yet powerful effect of Reiki — a Japanese energy healing technique that promotes relaxation and balances the body\'s energy. This hands-on healing method helps remove energy blockages, restore inner harmony, and reduce stress levels.';
          reikiDescPreview.textContent = reikiText;
        }
        
        // Sauna
        const saunaTitleField = document.getElementById('massage-sauna-title');
        const saunaDescField = document.getElementById('massage-sauna-description');
        const saunaTitlePreview = document.getElementById('preview-massage-sauna-title');
        const saunaDescPreview = document.getElementById('preview-massage-sauna-desc');
        if (saunaTitleField) saunaTitleField.value = data.massageSaunaTitle || '';
        if (saunaDescField) saunaDescField.value = data.massageSaunaDescription || '';
        if (saunaTitlePreview) saunaTitlePreview.textContent = data.massageSaunaTitle || 'Sauna';
        if (saunaDescPreview) {
          const saunaText = data.massageSaunaDescription || 'After a day spent in nature, sometimes you just want to warm up. We understand how important comfort is, so we offer our guests access to a small sauna. It is located right in the house, on the basement floor.';
          saunaDescPreview.textContent = saunaText;
        }
        
        const defMassageBookingTitle = 'Book a Massage or Sauna';
        const bookingTitleField = document.getElementById('massage-booking-title');
        const bookingIntroField = document.getElementById('massage-booking-intro');
        const bookingTitlePreview = document.getElementById('preview-massage-booking-title');
        const bookingIntroPreview = document.getElementById('preview-massage-booking-intro');
        const tBooking = (data.massageBookingTitle && String(data.massageBookingTitle).trim()) || '';
        const tIntro = (data.massageBookingIntro && String(data.massageBookingIntro).trim()) || '';
        if (bookingTitleField) bookingTitleField.value = tBooking;
        if (bookingIntroField) bookingIntroField.value = tIntro;
        if (bookingTitlePreview) bookingTitlePreview.textContent = tBooking || defMassageBookingTitle;
        if (bookingIntroPreview) bookingIntroPreview.textContent = tIntro;
        
        // Mini-hotel section — one description field (room_cards_settings.mini_hotel_description)
        const miniHotelTitleField = document.getElementById('mini-hotel-title');
        const miniHotelTitlePreview = document.getElementById('preview-mini-hotel-title');
        const miniHotelDescField = document.getElementById('mini-hotel-description');
        const miniHotelDescPreview = document.getElementById('preview-mini-hotel-desc');
        const inheritedMiniDescMerged = (() => {
          const a = exploreAdminPlainFromApi(data.miniHotelDescription1);
          const b = exploreAdminPlainFromApi(data.miniHotelDescription2);
          if (!a && !b) {
            return 'After your relaxing massage session, why not extend your stay? Our cozy mini-hotel offers comfortable rooms and apartments where you can fully unwind and enjoy the peaceful atmosphere of Back to Base.\n\nLocated just 35 km from Nelson, BC, surrounded by forest near Kootenay Lake with beautiful views of Mount Loki. Easy online booking — perfect for a peaceful vacation and retreat in nature.';
          }
          if (a && b) return a + '\n\n' + b;
          return b || a;
        })();
        window.__miniHotelDescriptionInherited = {
          'mini-hotel-description': inheritedMiniDescMerged
        };
        const apiMiniDesc = data.miniHotelDescription;
        console.log('Loading mini-hotel data from API:', {
          miniHotelTitle: data.miniHotelTitle,
          miniHotelDescription: data.miniHotelDescription
        });
        if (miniHotelTitleField) miniHotelTitleField.value = data.miniHotelTitle || '';
        if (miniHotelTitlePreview) miniHotelTitlePreview.textContent = data.miniHotelTitle || 'Book a room in our mini-hotel';
        if (miniHotelDescField) {
          miniHotelDescField.value = apiMiniDesc != null && String(apiMiniDesc).trim() !== '' ? apiMiniDesc : '';
        }
        if (miniHotelDescPreview) {
          miniHotelDescPreview.textContent =
            apiMiniDesc != null && String(apiMiniDesc).trim() !== '' ? apiMiniDesc : inheritedMiniDescMerged;
        }
        
        applyMassagePricingUIFromApiString('relaxing', data.massagePricingRelaxing);
        applyMassagePricingUIFromApiString('deep', data.massagePricingDeepTissue);
        applyMassagePricingUIFromApiString('reiki', data.massagePricingReiki);
        applyMassagePricingUIFromApiString('sauna', data.massagePricingSauna);
        ensureMassagePricingInputDelegation();
        
        // Load images
        await loadMassageImagesData(data);
      }
    }
  } catch (error) {
    console.log('Failed to load massage page data:', error);
  }
}

// Load massage page images
async function loadMassageImagesData(data = null) {
  console.log('Loading massage images data...');
  try {
    if (!data) {
      const formData = new FormData();
      formData.append('action', 'get_content');
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data) {
          data = result.data;
        }
      }
    }
    
    if (data) {
      const heroImageUrl = data.massageHeroImageUrl || '';
      const relaxingImageUrl = data.massageRelaxingImageUrl || '';
      const deepTissueImageUrl = data.massageDeepTissueImageUrl || '';
      const reikiImageUrl = data.massageReikiImageUrl || '';
      const saunaImageUrl = data.massageSaunaImageUrl || '';
      const miniHotelImageUrl = data.miniHotelImageUrl || '';
      
      // Update image previews in schematic preview
      const heroImg = document.getElementById('preview-massage-hero-img');
      if (heroImg && heroImageUrl) {
        heroImg.src = heroImageUrl + '?v=' + Date.now();
        heroImg.style.display = 'block';
        heroImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      const relaxingImg = document.getElementById('preview-massage-relaxing-img');
      if (relaxingImg && relaxingImageUrl) {
        relaxingImg.src = relaxingImageUrl + '?v=' + Date.now();
        relaxingImg.style.display = 'block';
        relaxingImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      const deepTissueImg = document.getElementById('preview-massage-deep-tissue-img');
      if (deepTissueImg && deepTissueImageUrl) {
        deepTissueImg.src = deepTissueImageUrl + '?v=' + Date.now();
        deepTissueImg.style.display = 'block';
        deepTissueImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      const reikiImg = document.getElementById('preview-massage-reiki-img');
      if (reikiImg && reikiImageUrl) {
        reikiImg.src = reikiImageUrl + '?v=' + Date.now();
        reikiImg.style.display = 'block';
        reikiImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      const saunaImg = document.getElementById('preview-massage-sauna-img');
      if (saunaImg && saunaImageUrl) {
        saunaImg.src = saunaImageUrl + '?v=' + Date.now();
        saunaImg.style.display = 'block';
        saunaImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      const miniHotelImg = document.getElementById('preview-mini-hotel-img');
      if (miniHotelImg && miniHotelImageUrl) {
        miniHotelImg.src = miniHotelImageUrl + '?v=' + Date.now();
        miniHotelImg.style.display = 'block';
        miniHotelImg.parentElement.querySelector('span').style.display = 'none';
      }
      
      // Save to localStorage
      const stored = localStorage.getItem('btb_massage_images') || '{}';
      const storedJson = JSON.parse(stored);
      const massageImagesData = {
        ...storedJson,
        hero: heroImageUrl || storedJson.hero || '',
        relaxing: relaxingImageUrl || storedJson.relaxing || '',
        deepTissue: deepTissueImageUrl || storedJson.deepTissue || '',
        reiki: reikiImageUrl || storedJson.reiki || '',
        sauna: saunaImageUrl || storedJson.sauna || '',
        miniHotel: miniHotelImageUrl || storedJson.miniHotel || ''
      };
      localStorage.setItem('btb_massage_images', JSON.stringify(massageImagesData));
      console.log('Massage images data saved to localStorage');
    }
  } catch (error) {
    console.log('Failed to load massage images data:', error);
  }
}

// Initialize massage image upload
function initMassageImageUpload() {
  const uploadConfigs = [
    {
      inputId: 'massage-hero-upload',
      previewImgId: 'preview-massage-hero-img',
      imageType: 'massage-hero'
    },
    {
      inputId: 'massage-relaxing-upload',
      previewImgId: 'preview-massage-relaxing-img',
      imageType: 'massage-relaxing'
    },
    {
      inputId: 'massage-deep-tissue-upload',
      previewImgId: 'preview-massage-deep-tissue-img',
      imageType: 'massage-deep-tissue'
    },
    {
      inputId: 'massage-reiki-upload',
      previewImgId: 'preview-massage-reiki-img',
      imageType: 'massage-reiki'
    },
    {
      inputId: 'massage-sauna-upload',
      previewImgId: 'preview-massage-sauna-img',
      imageType: 'massage-sauna'
    },
    {
      inputId: 'mini-hotel-image-upload',
      previewImgId: 'preview-mini-hotel-img',
      imageType: 'mini-hotel'
    }
  ];

  uploadConfigs.forEach(config => {
    const fileInput = document.getElementById(config.inputId);
    const previewImg = document.getElementById(config.previewImgId);

    console.log(`Initializing image upload for ${config.imageType}:`, {
      inputId: config.inputId,
      previewImgId: config.previewImgId,
      fileInputExists: !!fileInput,
      previewImgExists: !!previewImg
    });

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        console.log(`File selected for ${config.imageType}:`, file?.name);
        if (file && previewImg) {
          const previewContainer = previewImg.parentElement;
          const placeholderSpan = previewContainer.querySelector('span');
          
          console.log(`Starting upload for ${config.imageType}...`);
          await uploadImage(file, config.imageType, null, null, {
            localStorageKey: 'btb_massage_images',
            fieldNameMapper: (type) => {
              const typeMap = {
                'massage-hero': 'hero',
                'massage-relaxing': 'relaxing',
                'massage-deep-tissue': 'deepTissue',
                'massage-reiki': 'reiki',
                'massage-sauna': 'sauna',
                'mini-hotel': 'miniHotel'
              };
              return typeMap[type] || type;
            },
            reloadFunction: loadMassageImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'massage-hero': 'Hero',
                'massage-relaxing': 'Relaxing Massage',
                'massage-deep-tissue': 'Deep Tissue Massage',
                'massage-reiki': 'Reiki Energy Healing',
                'massage-sauna': 'Sauna',
                'mini-hotel': 'Mini-hotel'
              };
              return nameMap[type] || type;
            },
            onSuccess: (imageUrl) => {
              console.log(`Upload success for ${config.imageType}:`, imageUrl);
              if (previewImg) {
                previewImg.src = imageUrl + '?v=' + Date.now();
                previewImg.style.display = 'block';
                if (placeholderSpan) placeholderSpan.style.display = 'none';
              }
              // Trigger auto-save for images
              if (typeof window.scheduleMassageAutoSave === 'function') {
                window.scheduleMassageAutoSave();
              }
            },
            onError: (error) => {
              console.error(`Upload error for ${config.imageType}:`, error);
              alert(`Image upload error for ${config.imageType}: ${error}`);
            }
          });
        } else {
          console.warn(`No file selected or previewImg not found for ${config.imageType}`);
        }
      });
    } else {
      console.error(`File input not found: ${config.inputId}`);
    }
  });
}


function getDefaultMassageServices() {
  return [
    {
      name: 'Relaxing Massage',
      duration: 60,
      price: 110,
      type: 'relaxing',
      description: 'This gentle massage, perfect for those who want to unwind and restore their energy.'
    },
    {
      name: 'Relaxing Massage',
      duration: 90,
      price: 160,
      type: 'relaxing',
      description: 'Extended relaxing massage session for deeper relaxation.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 60,
      price: 120,
      type: 'deep-tissue',
      description: 'For targeted relief of muscle tension and pain.'
    },
    {
      name: 'Deep Tissue Massage',
      duration: 90,
      price: 170,
      type: 'deep-tissue',
      description: 'Extended deep tissue massage for chronic issues.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 15,
      price: 25,
      type: 'reiki',
      description: 'Gentle energy healing technique that promotes relaxation.'
    },
    {
      name: 'Reiki Energy Healing',
      duration: 30,
      price: 50,
      type: 'reiki',
      description: 'Extended Reiki session for deeper energy work.'
    }
  ];
}

// Yoga management
function loadYogaData() {
  const yogaList = document.getElementById('yoga-list');
  if (!yogaList) return;
  
  const yogaServices = getStoredData('btb_yoga_services') || getDefaultYogaServices();
  
  yogaList.innerHTML = yogaServices.map((service, index) => `
    <div class="admin-service-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
          <h4 style="margin: 0 0 8px 0; color: #2d3748;">${service.name}</h4>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Duration: ${service.duration} minutes</p>
          <p style="margin: 0 0 4px 0; color: #4a5568;">Price: ${service.price} CAD</p>
          <p style="margin: 0; color: #718096; font-size: 14px;">${service.description}</p>
        </div>
        <div style="display: flex; gap: 8px; margin-left: 16px;">
          <button class="admin-btn admin-btn-secondary" onclick="editYoga(${index})">Edit</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteYoga(${index})">Delete</button>
        </div>
      </div>
    </div>
  `).join('');
}

function getDefaultYogaServices() {
  return [
    {
      name: 'Individual Session',
      duration: 60,
      price: 90,
      type: 'individual',
      description: 'One-on-one yoga session in the forest with an experienced instructor.'
    },
    {
      name: 'Couple Session',
      duration: 60,
      price: 120,
      type: 'couple',
      description: 'Yoga session for couples in a peaceful forest setting.'
    },
    {
      name: 'Group Training',
      duration: 60,
      price: 0,
      type: 'group',
      description: 'Group yoga sessions - price and time arranged individually.'
    }
  ];
}

// Content management
async function loadContentData() {
  // Try to load from API first
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const content = result.data;
        
        document.getElementById('homepage-description').value = content.homepageDescription || '';
        document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
        // Do not inject HTML defaults into inputs when DB value is empty — that looked like "saved text reverted"
        // and combined with saveContentToServer could overwrite real DB data with placeholders.
        document.getElementById('contact-phone').value =
          content.contactPhone != null ? String(content.contactPhone) : '';
        document.getElementById('contact-email').value =
          content.contactEmail != null ? String(content.contactEmail) : '';
        document.getElementById('contact-address').value =
          content.contactAddress != null ? String(content.contactAddress) : '';
        return;
      }
    }
  } catch (error) {
    console.log('Failed to load from API, using localStorage');
  }
  
  // Fallback to localStorage
  const content = getStoredData('btb_content') || getDefaultContent();
  
  document.getElementById('homepage-description').value = content.homepageDescription || '';
  document.getElementById('homepage-subtitle').value = content.homepageSubtitle || '';
  document.getElementById('contact-phone').value =
    content.contactPhone != null ? String(content.contactPhone) : '';
  document.getElementById('contact-email').value =
    content.contactEmail != null ? String(content.contactEmail) : '';
  document.getElementById('contact-address').value =
    content.contactAddress != null ? String(content.contactAddress) : '';
}

function getDefaultContent() {
  return {
    homepageDescription: 'Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.',
    homepageSubtitle: 'Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.',
    contactPhone: DEFAULT_CONTACT_PHONE,
    contactEmail: DEFAULT_CONTACT_EMAIL,
    contactAddress: DEFAULT_CONTACT_ADDRESS
  };
}

// Images management
function loadImagesData() {
  const imagesContainer = document.getElementById('current-images');
  if (!imagesContainer) return;
  
  const images = getStoredData('btb_images') || [];
  
  imagesContainer.innerHTML = images.map((image, index) => `
    <div class="admin-image-item" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center;">
      <img src="${image.url}" alt="${image.name}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
      <p style="margin: 0 0 8px 0; font-size: 14px; color: #2d3748;">${image.name}</p>
      <button class="admin-btn admin-btn-danger" onclick="deleteImage(${index})">Delete</button>
    </div>
  `).join('');
}

// Utility functions
function getStoredData(key) {
  try {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : null;
  } catch (e) {
    return null;
  }
}

function setStoredData(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
    return true;
  } catch (e) {
    return false;
  }
}

function showStatus(message, type = 'success') {
  const el = document.getElementById('admin-save-banner');
  const sectionEl = document.getElementById('admin-save-banner-section');
  const iconEl = document.getElementById('admin-save-banner-icon');
  const msgEl = document.getElementById('admin-save-banner-message');
  if (el && sectionEl && iconEl && msgEl) {
    clearAdminGlobalSaveBarAutoHide();
    document.body.classList.add('admin-global-save-banner-padded');
    sectionEl.textContent = '';
    const ok = type === 'success' || type === 'ok';
    el.setAttribute('data-state', ok ? 'toast_success' : 'toast_error');
    iconEl.textContent = ok ? '✓' : '✗';
    msgEl.textContent = String(message);
    el.removeAttribute('hidden');
    el.classList.add('is-visible');
    el.removeAttribute('title');
    adminGlobalSaveBarAutoHideTimer = setTimeout(() => {
      hideAdminGlobalSaveBar();
    }, 3000);
    return;
  }
  const statusDiv = document.createElement('div');
  statusDiv.className = `admin-status ${type}`;
  statusDiv.textContent = message;
  const container = document.querySelector('.admin-container');
  if (container) {
    container.insertBefore(statusDiv, container.firstChild);
  }
  setTimeout(() => {
    statusDiv.remove();
  }, 3000);
}

// Form handlers
function initAdminForms() {
  // Add room form
  const addRoomForm = document.getElementById('add-room-form');
  if (addRoomForm) {
    addRoomForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const room = {
        name: document.getElementById('room-name').value,
        price: parseInt(document.getElementById('room-price').value),
        capacity: parseInt(document.getElementById('room-capacity').value),
        type: document.getElementById('room-type').value,
        description: document.getElementById('room-description').value
      };
      
      const rooms = getStoredData('btb_rooms') || getDefaultRooms();
      rooms.push(room);
      setStoredData('btb_rooms', rooms);
      
      showStatus('Room added successfully!');
      loadRoomsData();
      addRoomForm.reset();
    });
  }
  
  // Add yoga form
  const addYogaForm = document.getElementById('add-yoga-form');
  if (addYogaForm) {
    addYogaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const service = {
        name: document.getElementById('yoga-name').value,
        duration: parseInt(document.getElementById('yoga-duration').value),
        price: parseInt(document.getElementById('yoga-price').value),
        type: document.getElementById('yoga-type').value,
        description: document.getElementById('yoga-description').value
      };
      
      const services = getStoredData('btb_yoga_services') || getDefaultYogaServices();
      services.push(service);
      setStoredData('btb_yoga_services', services);
      
      showStatus('Yoga service added successfully!');
      loadYogaData();
      addYogaForm.reset();
    });
  }
  
  // Save content
  const saveContentBtn = document.getElementById('save-content');
  if (saveContentBtn) {
    saveContentBtn.addEventListener('click', async () => {
      // Read only fields on this form — do not merge getDefaultContent() (would overwrite DB with demo text/contact).
      const content = {
        homepageDescription: document.getElementById('homepage-description').value,
        homepageSubtitle: document.getElementById('homepage-subtitle').value,
        contactPhone: document.getElementById('contact-phone').value,
        contactEmail: document.getElementById('contact-email').value,
        contactAddress: document.getElementById('contact-address').value
      };
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content, {
          includeHomepageFields: true,
          includeContactFields: true,
          includeHeroUrls: false
        });
        if (saved) {
          setStoredData('btb_content', { ...(getStoredData('btb_content') || {}), ...content });
          showStatus('Content saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', { ...(getStoredData('btb_content') || {}), ...content });
      showStatus('Content saved successfully!');
    });
  }
  
  // Save contact
  const saveContactBtn = document.getElementById('save-contact');
  if (saveContactBtn) {
    saveContactBtn.addEventListener('click', async () => {
      const content = {
        contactPhone: document.getElementById('contact-phone').value,
        contactEmail: document.getElementById('contact-email').value,
        contactAddress: document.getElementById('contact-address').value
      };
      
      // Try to save to server first
      try {
        const saved = await saveContentToServer(content, {
          includeHomepageFields: false,
          includeContactFields: true,
          includeHeroUrls: false
        });
        if (saved) {
          setStoredData('btb_content', { ...(getStoredData('btb_content') || {}), ...content });
          showStatus('Contact information saved successfully!');
          return;
        }
      } catch (error) {
        console.log('Server save failed, saving to localStorage');
      }
      
      // Fallback to localStorage
      setStoredData('btb_content', { ...(getStoredData('btb_content') || {}), ...content });
      showStatus('Contact information saved successfully!');
    });
  }
}

// Save content to server via API.
// By default only send homepage + contact; omit hero URLs unless includeHeroUrls (avoids wiping hero when saving contact).
async function saveContentToServer(content, options = {}) {
  const {
    includeHomepageFields = true,
    includeContactFields = true,
    includeHeroUrls = false
  } = options;
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    if (includeHomepageFields) {
      formData.append('homepage_description', content.homepageDescription ?? '');
      formData.append('homepage_subtitle', content.homepageSubtitle ?? '');
    }
    if (includeContactFields) {
      formData.append('contact_phone', content.contactPhone ?? '');
      formData.append('contact_email', content.contactEmail ?? '');
      formData.append('contact_address', content.contactAddress ?? '');
    }
    if (includeHeroUrls) {
      formData.append('hero_image_url', content.heroImageUrl ?? '');
      formData.append('hero2_image_url', content.hero2ImageUrl ?? '');
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    const rawText = await response.text();
    const result = parseJsonFromText(rawText);
    return response.ok && isApiSaveSuccess(result);
  } catch (error) {
    console.error('Error saving to server:', error);
    return false;
  }
}

// Image upload
function initImageUpload() {
  const uploadArea = document.getElementById('image-upload-area');
  const fileInput = document.getElementById('image-upload');
  const previewContainer = document.getElementById('image-preview-container');
  
  if (!uploadArea || !fileInput) return;
  
  uploadArea.addEventListener('click', () => fileInput.click());
  
  uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
  });
  
  uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
  });
  
  uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
  });
  
  fileInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
  });
  
  function handleFiles(files) {
    Array.from(files).forEach(file => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
          const imageDiv = document.createElement('div');
          imageDiv.style.cssText = 'display: inline-block; margin: 8px; text-align: center;';
          imageDiv.innerHTML = `
            <img src="${e.target.result}" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-bottom: 8px;">
            <p style="margin: 0; font-size: 12px; color: #4a5568;">${file.name}</p>
          `;
          previewContainer.appendChild(imageDiv);
        };
        reader.readAsDataURL(file);
      }
    });
  }
}

// Initialize floorplan image upload functionality
function initFloorplanImageUpload() {
  const uploadConfigs = [
    {
      buttonId: 'basement-upload-btn',
      inputId: 'basement-image-upload',
      previewId: 'basement-image-preview',
      pathId: 'basement-image-path',
      imageType: 'basement'
    },
    {
      buttonId: 'ground-upload-btn',
      inputId: 'ground-image-upload',
      previewId: 'ground-image-preview',
      pathId: 'ground-image-path',
      imageType: 'ground'
    },
    {
      buttonId: 'loft-upload-btn',
      inputId: 'loft-image-upload',
      previewId: 'loft-image-preview',
      pathId: 'loft-image-path',
      imageType: 'loft'
    }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = config.buttonId ? document.getElementById(config.buttonId) : null;
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (!fileInput) {
      console.warn(`Floorplan upload input not found: ${config.inputId}`);
      return;
    }

    if (uploadBtn) {
      uploadBtn.addEventListener('click', () => fileInput.click());
    }

    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        await uploadFloorplanImage(file, config.imageType, preview, pathDisplay);
      }
    });
  });
}

// Universal image upload function
// Works for all sections: Floor Plan, Home Page, etc.
// config: { localStorageKey, fieldNameMapper, reloadFunction, imageNameMapper }
async function uploadImage(file, imageType, previewElement, pathElement, config = {}) {
  const {
    localStorageKey = 'btb_floorplan_settings',
    fieldNameMapper = (type) => type + '_image_url',
    reloadFunction = null,
    imageNameMapper = (type) => type.charAt(0).toUpperCase() + type.slice(1)
  } = config;

  const formData = new FormData();
  formData.append('image', file);
  formData.append('image_type', imageType);

  try {
    console.log(`Uploading ${imageType} image:`, file.name);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    // Check if response is OK
    if (!response.ok) {
      const text = await response.text();
      console.error(`Upload failed: HTTP ${response.status}`, text);
      const errorMsg = `Upload failed: ${response.status} ${response.statusText}\n${text.substring(0, 200)}`;
      alert(errorMsg);
      if (config.onError && typeof config.onError === 'function') {
        config.onError(errorMsg);
      }
      return;
    }

    let result;
    try {
      result = await response.json();
    } catch (e) {
      const text = await response.text();
      console.error('Failed to parse JSON response:', text);
      const errorMsg = `Upload failed: Invalid server response\n${text.substring(0, 200)}`;
      alert(errorMsg);
      if (config.onError && typeof config.onError === 'function') {
        config.onError(errorMsg);
      }
      return;
    }
    // Some endpoints wrap payload under data; normalize
    const payload = result && result.data ? result.data : result;
    const filepath = payload && payload.filepath ? payload.filepath : (payload && payload.imageUrl ? payload.imageUrl : '');
    
    if (!result.success) {
      const errorMsg = result.error || result.message || 'Upload failed';
      console.error('Upload failed:', errorMsg);
      alert(`Upload failed: ${errorMsg}`);
      if (config.onError && typeof config.onError === 'function') {
        config.onError(errorMsg);
      }
      return;
    }
    
    if (result.success) {
      console.log('Image uploaded successfully:', result);
      
      // Show preview (only if previewElement is provided)
      if (previewElement) {
        const img = document.createElement('img');
        img.src = filepath + '?v=' + Date.now();
        previewElement.innerHTML = '';
        previewElement.appendChild(img);
        previewElement.style.display = 'block';
      }
      
      // Show path (only if pathElement is provided)
      if (pathElement) {
        pathElement.textContent = filepath;
        pathElement.style.display = 'block';
      }
      
      // Update schematic preview images
      const previewImageMap = {
        'homepage-hero': 'preview-homepage-hero-img',
        'homepage-hero2': 'preview-homepage-hero2-img',
        'basement': 'preview-basement-floor-img',
        'ground': 'preview-ground-floor-img',
        'loft': 'preview-loft-floor-img',
        'room-basement-card': 'preview-room-basement-card-img',
        'room-ground-queen-card': 'preview-room-ground-queen-card-img',
        'room-ground-twin-card': 'preview-room-ground-twin-card-img',
        'room-second-card': 'preview-room-second-card-img',
        'retreat-hero': 'preview-retreat-hero-img',
        'retreat-forest': 'preview-retreat-forest-img',
        'retreat-indoor': 'preview-retreat-indoor-img',
        'retreat-theatre': 'preview-retreat-theatre-img',
        'retreat-collaboration': 'preview-retreat-collaboration-img',
        'special-hero': 'preview-special-hero-img',
        'special-pools': 'preview-special-pools-img',
        'special-dining': 'preview-special-dining-img',
        'special-extra': 'preview-special-extra-img',
        'about-hero': 'preview-about-hero-img',
        'about-founder': 'preview-about-founder-img',
        'about-procter': 'preview-about-procter-img',
        'about-nelson': 'preview-about-nelson-img',
        'about-kaslo': 'preview-about-kaslo-img',
        'about-crawford': 'preview-about-crawford-img',
        'about-museum': 'preview-about-museum-img',
        'explore-hero': 'preview-explore-hero-img',
        'explore-accommodation': 'preview-explore-accommodation-img',
        'massage-hero': 'preview-massage-hero-img',
        'massage-relaxing': 'preview-massage-relaxing-img',
        'massage-deep-tissue': 'preview-massage-deep-tissue-img',
        'massage-reiki': 'preview-massage-reiki-img',
        'massage-sauna': 'preview-massage-sauna-img',
        'mini-hotel': 'preview-mini-hotel-img',
        'wellness-massage': 'preview-wellness-massage-img'
      };
      
      let previewImgId = previewImageMap[imageType];
      if (!previewImgId) {
        const parkHeroMatch = String(imageType).match(/^about-park-card-(\d+)-hero$/);
        if (parkHeroMatch) {
          previewImgId = `preview-about-park-card-${parkHeroMatch[1]}-hero-img`;
        }
        const secHeroMatch = String(imageType).match(/^explore-(communities|culture|activities)-card-(\d+)-hero$/);
        if (secHeroMatch) {
          previewImgId = `preview-explore-${secHeroMatch[1]}-card-${secHeroMatch[2]}-hero-img`;
        }
      }
      if (previewImgId) {
        const previewImg = document.getElementById(previewImgId);
        if (previewImg) {
          previewImg.src = filepath + '?v=' + Date.now();
          previewImg.style.display = 'block';
          const span = previewImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
        }
      }
      
      // Update localStorage for immediate site update
      let storedData = localStorage.getItem(localStorageKey);
      let data = {};
      if (storedData) {
        try {
          data = JSON.parse(storedData);
        } catch (e) {
          console.error('Failed to parse localStorage data:', e);
          data = {};
        }
      }
      
      // Update field using mapper
      const fieldName = fieldNameMapper(imageType);
      
      // Special handling for wellness experiences - save in nested structure
      if (localStorageKey === 'btb_wellness_experiences' && imageType.startsWith('wellness-')) {
        const wellnessType = imageType.replace('wellness-', '');
        if (!data[wellnessType]) {
          data[wellnessType] = {};
        }
        data[wellnessType].imageUrl = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const wellnessFieldName = 'wellness' + wellnessType.charAt(0).toUpperCase() + wellnessType.slice(1) + 'ImageUrl';
        contentData[wellnessFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${wellnessFieldName} = ${filepath}`);
      } else if (localStorageKey === 'btb_homepage_rooms' && imageType.startsWith('room-') && imageType.endsWith('-card')) {
        // Special handling for room cards - save in nested structure
        const roomType = imageType.replace('room-', '').replace('-card', '');
        // Map to correct keys: basement, groundQueen, groundTwin, second
        let roomKey = roomType;
        if (roomType === 'ground-queen') roomKey = 'groundQueen';
        else if (roomType === 'ground-twin') roomKey = 'groundTwin';
        
        if (!data[roomKey]) {
          data[roomKey] = {};
        }
        data[roomKey].imageUrl = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const roomFieldName = 'room' + roomKey.charAt(0).toUpperCase() + roomKey.slice(1) + 'CardImageUrl';
        contentData[roomFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${roomFieldName} = ${filepath}`);
        
        // Save to database (room_cards_settings table)
        // Map roomType to database field name
        const dbFieldMap = {
          'basement': 'room_basement_card_image_url',
          'ground-queen': 'room_ground_queen_card_image_url',
          'ground-twin': 'room_ground_twin_card_image_url',
          'second': 'room_second_card_image_url'
        };
        const dbFieldName = dbFieldMap[roomType] || ('room_' + roomType.replace(/-/g, '_') + '_card_image_url');
        console.log(`Saving room card image to database: ${dbFieldName} = ${filepath}`);
        try {
          const saveFormData = new FormData();
          saveFormData.append('action', 'save_content');
          saveFormData.append(dbFieldName, filepath);
          
          const saveResponse = await fetch('api.php', {
            method: 'POST',
            body: saveFormData
          });
          
          if (saveResponse.ok) {
            const saveResult = await saveResponse.json();
            if (saveResult.success) {
              console.log(`✓ Saved room card image to database: ${dbFieldName} = ${filepath}`);
              // Reload data to get updated image from database
              if (reloadFunction && typeof reloadFunction === 'function') {
                setTimeout(() => {
                  console.log('Reloading homepage rooms data after image save...');
                  reloadFunction();
                }, 500);
              }
            } else {
              console.error('Failed to save room card image to database:', saveResult.error);
            }
          } else {
            const errorText = await saveResponse.text();
            console.error('Failed to save room card image to database: HTTP error', errorText);
          }
        } catch (error) {
          console.error('Error saving room card image to database:', error);
        }
      } else if (localStorageKey === 'btb_massage_images' && imageType.startsWith('massage-')) {
        // Special handling for massage images - save in flat structure
        const massageType = imageType.replace('massage-', '');
        const typeMap = {
          'relaxing': 'relaxing',
          'deep-tissue': 'deepTissue',
          'reiki': 'reiki',
          'sauna': 'sauna',
          'room-booking': 'roomBooking'
        };
        const mappedType = typeMap[massageType] || massageType;
        data[mappedType] = filepath;
        
        // Also save to btb_content for site display
        let contentData = {};
        const contentStored = localStorage.getItem('btb_content');
        if (contentStored) {
          try {
            contentData = JSON.parse(contentStored);
          } catch (e) {
            console.error('Failed to parse btb_content:', e);
          }
        }
        const massageFieldName = 'massage' + mappedType.charAt(0).toUpperCase() + mappedType.slice(1) + 'ImageUrl';
        contentData[massageFieldName] = filepath;
        localStorage.setItem('btb_content', JSON.stringify(contentData));
        console.log(`Also saved to btb_content: ${massageFieldName} = ${filepath}`);
      } else if (localStorageKey === 'btb_content' && (imageType === 'homepage-hero' || imageType === 'homepage-hero2')) {
        // Special handling for homepage hero images
        const homepageFieldName = imageType === 'homepage-hero' ? 'hero_image_url' : 'hero2_image_url';
        data[homepageFieldName] = filepath;
        
        // Also update hidden field
        const hiddenFieldId = imageType === 'homepage-hero' ? 'homepage-hero-image-url' : 'homepage-hero2-image-url';
        const hiddenField = document.getElementById(hiddenFieldId);
        if (hiddenField) {
          hiddenField.value = filepath;
        }
      } else if (localStorageKey === 'btb_retreat_images' && imageType.startsWith('retreat-')) {
        data[fieldName] = filepath;
        const retreatContentKeyMap = {
          'retreat-hero': 'retreatHeroImageUrl',
          'retreat-collaboration': 'retreatCollaborationImageUrl',
          'retreat-forest': 'retreatForestImageUrl',
          'retreat-indoor': 'retreatIndoorImageUrl',
          'retreat-theatre': 'retreatTheatreImageUrl'
        };
        const ckey = retreatContentKeyMap[imageType];
        if (ckey) {
          let contentData = {};
          const contentStored = localStorage.getItem('btb_content');
          if (contentStored) {
            try {
              contentData = JSON.parse(contentStored);
            } catch (e) {
              console.error('Failed to parse btb_content:', e);
            }
          }
          contentData[ckey] = filepath;
          localStorage.setItem('btb_content', JSON.stringify(contentData));
          console.log(`Also saved to btb_content: ${ckey} = ${filepath}`);
        }
      } else {
        // Standard flat structure
        data[fieldName] = filepath;
        
        // For hero images, also ensure they're in btb_content
        if (localStorageKey === 'btb_content' && (imageType === 'hero' || imageType === 'hero2')) {
          // Already saving to btb_content, no need to duplicate
        }
      }
      
      localStorage.setItem(localStorageKey, JSON.stringify(data));
      console.log(`Updated localStorage (${localStorageKey}) with new image path:`, filepath);
      
      const imageName = imageNameMapper(imageType);
      showStatus(`${imageName} image uploaded successfully!`);
      
      // Call onSuccess callback if provided
      if (config.onSuccess && typeof config.onSuccess === 'function') {
        config.onSuccess(filepath);
      }
      
      // Force reload of data to get updated paths
      if (reloadFunction && typeof reloadFunction === 'function') {
        setTimeout(() => {
          reloadFunction();
        }, 1000);
      }
      
    } else {
      console.error('Upload failed:', result.error);
      const errorMsg = result.error || result.message || 'Upload failed';
      showStatus(`Upload failed: ${errorMsg}`, 'error');
      if (config.onError && typeof config.onError === 'function') {
        config.onError(errorMsg);
      }
    }
  } catch (error) {
    console.error('Upload error:', error);
    const errorMsg = error.message || 'Upload failed';
    showStatus('Upload failed: ' + errorMsg, 'error');
    if (config.onError && typeof config.onError === 'function') {
      config.onError(errorMsg);
    }
  }
}

// Upload floorplan image function (uses universal function)
async function uploadFloorplanImage(file, imageType, previewElement, pathElement) {
  return uploadImage(file, imageType, previewElement, pathElement, {
    localStorageKey: 'btb_floorplan_settings',
    fieldNameMapper: (type) => type + '_image_url',
    reloadFunction: loadFloorplanData,
    imageNameMapper: (type) => type.charAt(0).toUpperCase() + type.slice(1)
  });
}

// Initialize homepage image upload functionality (Floor Plan and Rooms only)
// Note: Hero images are now handled by the new initHomepageImageUpload() function
function initHomepageImageUploadLegacy() {
  // Floor Plan images
  const floorplanUploadConfigs = [
    {
      buttonId: 'basement-upload-btn',
      inputId: 'basement-image-upload',
      previewId: 'basement-image-preview',
      pathId: 'basement-image-path',
      imageType: 'basement'
    },
    {
      buttonId: 'ground-upload-btn',
      inputId: 'ground-image-upload',
      previewId: 'ground-image-preview',
      pathId: 'ground-image-path',
      imageType: 'ground'
    },
    {
      buttonId: 'loft-upload-btn',
      inputId: 'loft-image-upload',
      previewId: 'loft-image-preview',
      pathId: 'loft-image-path',
      imageType: 'loft'
    }
  ];

  floorplanUploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadFloorplanImage(file, config.imageType, preview, pathDisplay);
        }
      });
    }
  });

  // Rooms cards images
  const roomsUploadConfigs = [
    {
      buttonId: 'room-basement-card-upload-btn',
      inputId: 'room-basement-card-upload',
      previewId: 'room-basement-card-preview',
      pathId: 'room-basement-card-path',
      imageType: 'room-basement-card'
    },
    {
      buttonId: 'room-ground-queen-card-upload-btn',
      inputId: 'room-ground-queen-card-upload',
      previewId: 'room-ground-queen-card-preview',
      pathId: 'room-ground-queen-card-path',
      imageType: 'room-ground-queen-card'
    },
    {
      buttonId: 'room-ground-twin-card-upload-btn',
      inputId: 'room-ground-twin-card-upload',
      previewId: 'room-ground-twin-card-preview',
      pathId: 'room-ground-twin-card-path',
      imageType: 'room-ground-twin-card'
    },
    {
      buttonId: 'room-second-card-upload-btn',
      inputId: 'room-second-card-upload',
      previewId: 'room-second-card-preview',
      pathId: 'room-second-card-path',
      imageType: 'room-second-card'
    }
  ];

  roomsUploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => {
        fileInput.click();
      });

      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_homepage_rooms',
            fieldNameMapper: (type) => type.replace('room-', '').replace('-card', '') + 'CardImageUrl',
            reloadFunction: loadHomepageRoomsData,
            imageNameMapper: (type) => type.replace('room-', '').replace('-card', '').charAt(0).toUpperCase() + type.replace('room-', '').replace('-card', '').slice(1) + ' Card'
          });
        }
      });
    }
  });
}

// Upload homepage image function (uses universal function)
async function uploadHomepageImage(file, imageType, previewElement, pathElement) {
  return uploadImage(file, imageType, previewElement, pathElement, {
    localStorageKey: 'btb_content',
    fieldNameMapper: (type) => type === 'hero' ? 'heroImageUrl' : 'hero2ImageUrl',
    reloadFunction: loadHomepageData,
    imageNameMapper: (type) => type === 'hero' ? 'Hero' : 'Hero 2'
  });
}

// ==========================================
// ROOM PAGES MANAGEMENT
// ==========================================

/** Max images per gallery (room photos + common areas) on room detail CMS sections. */
const ROOM_PAGE_GALLERY_MAX_PHOTOS = 30;

/** DB may store HTML (<strong>Price:</strong> …); admin editors need plain text + formatted preview. */
function stripHtmlToPlainText(value) {
  if (value == null) return '';
  const s = String(value);
  if (!s.trim()) return '';
  const d = document.createElement('div');
  d.innerHTML = s;
  return (d.textContent || '').replace(/\s+/g, ' ').trim();
}

function escapeHtmlForAdminPreview(value) {
  const d = document.createElement('div');
  d.textContent = value == null ? '' : String(value);
  return d.innerHTML;
}

/**
 * @param {'price'|'capacity'} kind
 * @param {string} fallbackPlain text shown after label when API value is empty
 */
function applyRoomBookingLineFromApi(kind, rawFromApi, hiddenEl, previewEl, fallbackPlain) {
  const plain = stripHtmlToPlainText(rawFromApi);
  if (hiddenEl) {
    hiddenEl.value = plain;
  }
  if (!previewEl) {
    return;
  }
  const label = kind === 'price' ? '<strong>Price:</strong> ' : '<strong>Capacity:</strong> ';
  const body = plain || fallbackPlain;
  previewEl.innerHTML = label + escapeHtmlForAdminPreview(body);
}

function updateRoomPagePriceLinePreview(prefixId, amountId, suffixId, previewId) {
  const pe = document.getElementById(prefixId);
  const ae = document.getElementById(amountId);
  const se = document.getElementById(suffixId);
  const pr = document.getElementById(previewId);
  if (!pr) {
    return;
  }
  const p = pe ? String(pe.value || '').trim() : '';
  const a = ae ? String(ae.value || '').trim() : '';
  const s = se ? String(se.value || '').trim() : '';
  const chunks = [];
  if (p) {
    chunks.push(`<strong>${escapeHtmlForAdminPreview(p)}</strong>`);
  }
  if (a) {
    chunks.push(escapeHtmlForAdminPreview(a));
  }
  if (s) {
    chunks.push(escapeHtmlForAdminPreview(s));
  }
  pr.innerHTML = chunks.join(' ').trim();
}

function applyRoomPriceTripletFromApi(data, slug) {
  const cfg = {
    basement: {
      prefix: 'room-basement-price-prefix',
      amount: 'room-basement-price-amount',
      suffix: 'room-basement-price-suffix',
      preview: 'preview-room-basement-price',
      pk: 'roomBasementPricePrefix',
      ak: 'roomBasementPriceAmount',
      sk: 'roomBasementPriceSuffix',
      lk: 'roomBasementPrice'
    },
    ground_queen: {
      prefix: 'room-ground-queen-price-prefix',
      amount: 'room-ground-queen-price-amount',
      suffix: 'room-ground-queen-price-suffix',
      preview: 'preview-room-ground-queen-price',
      pk: 'roomGroundQueenPricePrefix',
      ak: 'roomGroundQueenPriceAmount',
      sk: 'roomGroundQueenPriceSuffix',
      lk: 'roomGroundQueenPrice'
    },
    ground_twin: {
      prefix: 'room-ground-twin-price-prefix',
      amount: 'room-ground-twin-price-amount',
      suffix: 'room-ground-twin-price-suffix',
      preview: 'preview-room-ground-twin-price',
      pk: 'roomGroundTwinPricePrefix',
      ak: 'roomGroundTwinPriceAmount',
      sk: 'roomGroundTwinPriceSuffix',
      lk: 'roomGroundTwinPrice'
    },
    second: {
      prefix: 'room-second-price-prefix',
      amount: 'room-second-price-amount',
      suffix: 'room-second-price-suffix',
      preview: 'preview-room-second-price',
      pk: 'roomSecondPricePrefix',
      ak: 'roomSecondPriceAmount',
      sk: 'roomSecondPriceSuffix',
      lk: 'roomSecondPrice'
    }
  }[slug];
  if (!cfg) {
    return;
  }
  const pe = document.getElementById(cfg.prefix);
  const ae = document.getElementById(cfg.amount);
  const se = document.getElementById(cfg.suffix);
  const pr = document.getElementById(cfg.preview);
  if (pe) {
    pe.value = data[cfg.pk] != null ? String(data[cfg.pk]) : '';
  }
  if (ae) {
    ae.value = data[cfg.ak] != null ? String(data[cfg.ak]) : '';
  }
  if (se) {
    se.value = data[cfg.sk] != null ? String(data[cfg.sk]) : '';
  }
  if (pr) {
    const line = data[cfg.lk] != null ? String(data[cfg.lk]) : '';
    pr.innerHTML = line.trim() !== '' ? line : '';
    if (pr.innerHTML.trim() === '') {
      updateRoomPagePriceLinePreview(cfg.prefix, cfg.amount, cfg.suffix, cfg.preview);
    }
  }
}

function initRoomPagePriceTripletInputsOnce() {
  if (window.__btbRoomPagePriceTripletsWired) {
    return;
  }
  window.__btbRoomPagePriceTripletsWired = true;
  const rows = [
    {
      prefix: 'room-basement-price-prefix',
      amount: 'room-basement-price-amount',
      suffix: 'room-basement-price-suffix',
      preview: 'preview-room-basement-price',
      schedule() {
        if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
          roomBasementHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomBasementAutoSave === 'function') {
          window.scheduleRoomBasementAutoSave();
        }
      }
    },
    {
      prefix: 'room-ground-queen-price-prefix',
      amount: 'room-ground-queen-price-amount',
      suffix: 'room-ground-queen-price-suffix',
      preview: 'preview-room-ground-queen-price',
      schedule() {
        if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
          roomGroundQueenHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
          window.scheduleRoomGroundQueenAutoSave();
        }
      }
    },
    {
      prefix: 'room-ground-twin-price-prefix',
      amount: 'room-ground-twin-price-amount',
      suffix: 'room-ground-twin-price-suffix',
      preview: 'preview-room-ground-twin-price',
      schedule() {
        if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
          roomGroundTwinHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
          window.scheduleRoomGroundTwinAutoSave();
        }
      }
    },
    {
      prefix: 'room-second-price-prefix',
      amount: 'room-second-price-amount',
      suffix: 'room-second-price-suffix',
      preview: 'preview-room-second-price',
      schedule() {
        if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
          roomSecondHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomSecondAutoSave === 'function') {
          window.scheduleRoomSecondAutoSave();
        }
      }
    }
  ];
  rows.forEach(({ prefix, amount, suffix, preview, schedule }) => {
    [prefix, amount, suffix].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) {
        return;
      }
      const run = () => {
        updateRoomPagePriceLinePreview(prefix, amount, suffix, preview);
        schedule();
      };
      el.addEventListener('input', run);
      el.addEventListener('change', run);
    });
  });
}

// Load room basement data
// Load room basement data (text, banner, gallery)
async function loadRoomBasementData() {
  console.log('Loading room basement page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        const titleField = document.getElementById('room-basement-title');
        const subtitleField = document.getElementById('room-basement-subtitle');
        const titlePreview = document.getElementById('preview-room-basement-title');
        const subtitlePreview = document.getElementById('preview-room-basement-subtitle');
        if (titleField) titleField.value = data.roomBasementTitle || '';
        if (subtitleField) subtitleField.value = data.roomBasementSubtitle || '';
        if (titlePreview) titlePreview.textContent = data.roomBasementTitle || 'Loki Suite';
        if (subtitlePreview) subtitlePreview.textContent = data.roomBasementSubtitle || 'A cozy room next to the home cinema and sauna. Ideal for two.';
        
        // Banner image
        const bannerImg = document.getElementById('preview-room-basement-banner-img');
        const bannerImageUrlField = document.getElementById('room-basement-banner-image-url');
        const bannerImageUrl = result.data.roomBasementBannerImageUrl || '';
        console.log('Loading banner from API:', bannerImageUrl);
        if (bannerImageUrlField) {
          bannerImageUrlField.value = bannerImageUrl;
          console.log('Banner URL field updated:', bannerImageUrl);
        }
        if (bannerImg && bannerImageUrl) {
          bannerImg.src = bannerImageUrl + '?v=' + Date.now();
          bannerImg.style.display = 'block';
          const span = bannerImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
          console.log('Banner preview updated from API');
        } else if (bannerImg && !bannerImageUrl) {
          console.log('No banner URL in API, keeping default');
        }
        
        // Gallery
        let gallery = [];
        try {
          gallery = JSON.parse(data.roomBasementGallery || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        updateRoomBasementGalleryPreview(gallery);
        const galleryField = document.getElementById('room-basement-gallery');
        if (galleryField) galleryField.value = JSON.stringify(gallery);

        const roomGalTitleEl = document.getElementById('room-basement-gallery-section-title');
        const commonGalTitleEl = document.getElementById('room-basement-common-gallery-section-title');
        if (roomGalTitleEl) {
          roomGalTitleEl.value =
            data.roomBasementGallerySectionTitle != null && String(data.roomBasementGallerySectionTitle).trim() !== ''
              ? String(data.roomBasementGallerySectionTitle)
              : 'Room photos';
        }
        if (commonGalTitleEl) {
          commonGalTitleEl.value =
            data.roomBasementCommonGallerySectionTitle != null &&
            String(data.roomBasementCommonGallerySectionTitle).trim() !== ''
              ? String(data.roomBasementCommonGallerySectionTitle)
              : 'Common areas photos';
        }
        let commonGallery = [];
        try {
          commonGallery = JSON.parse(data.roomBasementCommonGallery || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }
        updateRoomBasementCommonGalleryPreview(commonGallery);
        const commonGalleryField = document.getElementById('room-basement-common-gallery');
        if (commonGalleryField) commonGalleryField.value = JSON.stringify(commonGallery);
        
        // Booking card
        const capacityField = document.getElementById('room-basement-capacity');
        const descField = document.getElementById('room-basement-description');
        const noteField = document.getElementById('room-basement-note');
        const capacityPreview = document.getElementById('preview-room-basement-capacity');
        const descPreview = document.getElementById('preview-room-basement-desc');
        const notePreview = document.getElementById('preview-room-basement-note');
        if (descField) descField.value = data.roomBasementDescription || '';
        if (noteField) noteField.value = data.roomBasementNote || '';
        applyRoomPriceTripletFromApi(data, 'basement');
        applyRoomBookingLineFromApi('capacity', data.roomBasementCapacity, capacityField, capacityPreview, 'up to 2 guests');
        if (descPreview) {
          const desc = data.roomBasementDescription || 'Next to this room there is a home theater lounge with a wood-burning stove and a large shower area with a sauna. The floor has a private exit from the house and a passage to the shared lounge on the first floor.';
          // Replace newlines with <br> for display in contenteditable
          descPreview.textContent = desc;
        }
        if (notePreview) notePreview.textContent = data.roomBasementNote || '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.';
      }
    }
  } catch (error) {
    console.log('Failed to load room basement page data:', error);
  }
}

// Update gallery preview
function updateRoomBasementGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-basement-gallery-preview');
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceBasementGalleryImage(index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteBasementGalleryImage(index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if below max (room pages)
  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-basement-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

function updateRoomBasementCommonGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-basement-common-gallery-preview');
  if (!galleryPreview) return;

  galleryPreview.innerHTML = '';

  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText =
      'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';

    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText =
      'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceBasementCommonGalleryImage(index);
    };

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText =
      'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteBasementCommonGalleryImage(index);
    };

    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });

  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText =
      'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-basement-common-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

window.replaceBasementCommonGalleryImage = function (index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadBasementCommonGalleryImage(file, index);
    }
  };
  input.click();
};

window.deleteBasementCommonGalleryImage = function (index) {
  const galleryField = document.getElementById('room-basement-common-gallery');
  if (!galleryField) return;

  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse common gallery:', e);
    return;
  }

  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomBasementCommonGalleryPreview(gallery);

  if (typeof window.scheduleRoomBasementAutoSave === 'function') {
    if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
      roomBasementHasUnsavedChanges = true;
    }
    window.scheduleRoomBasementAutoSave();
  }
};

async function uploadBasementCommonGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-basement-common-gallery');
    formData.append('image', file);

    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl
        ? payload.imageUrl
        : payload && payload.filepath
          ? payload.filepath
          : result.imageUrl || result.filepath || '';
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-basement-common-gallery');
        if (!galleryField) return;

        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }

        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
          gallery.push(imageUrl);
        } else {
          alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
          return;
        }

        galleryField.value = JSON.stringify(gallery);
        updateRoomBasementCommonGalleryPreview(gallery);

        if (typeof window.scheduleRoomBasementAutoSave === 'function') {
          if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
            roomBasementHasUnsavedChanges = true;
          }
          window.scheduleRoomBasementAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading common gallery image:', error);
  }
}

// Replace gallery image
window.replaceBasementGalleryImage = function(index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadBasementGalleryImage(file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteBasementGalleryImage = function(index) {
  const galleryField = document.getElementById('room-basement-gallery');
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomBasementGalleryPreview(gallery);
  
  if (typeof window.scheduleRoomBasementAutoSave === 'function') {
    if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
      roomBasementHasUnsavedChanges = true;
    }
    window.scheduleRoomBasementAutoSave();
  }
};

// Upload gallery image
async function uploadBasementGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-basement-gallery');
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      // Extract imageUrl from response (can be in result.data or result directly)
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log('Gallery image upload result:', result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-basement-gallery');
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
            gallery.push(imageUrl);
          } else {
            alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateRoomBasementGalleryPreview(gallery);
        
        if (typeof window.scheduleRoomBasementAutoSave === 'function') {
          if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
            roomBasementHasUnsavedChanges = true;
          }
          window.scheduleRoomBasementAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading gallery image:', error);
  }
}

// Initialize room basement image upload
function initRoomBasementImageUpload() {
  // Banner upload
  const bannerInput = document.getElementById('room-basement-banner-upload');
  if (bannerInput) {
    bannerInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        const bannerImg = document.getElementById('preview-room-basement-banner-img');
        await uploadImage(file, 'basement-banner', null, null, {
          localStorageKey: 'btb_room_basement',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomBasementData,
          imageNameMapper: () => 'Basement Banner',
          onSuccess: (imageUrl) => {
            console.log('Banner uploaded successfully, URL:', imageUrl);
            const bannerImageUrlField = document.getElementById('room-basement-banner-image-url');
            if (bannerImageUrlField) {
              bannerImageUrlField.value = imageUrl;
              console.log('Banner URL saved to hidden field:', imageUrl);
            }
            if (bannerImg) {
              bannerImg.src = imageUrl + '?v=' + Date.now();
              bannerImg.style.display = 'block';
              const span = bannerImg.parentElement.querySelector('span');
              if (span) span.style.display = 'none';
              console.log('Banner preview updated in admin');
            }
            // Banner is saved automatically by upload_image.php to room_basement_banner_image_url
            // Reload data to ensure consistency
            setTimeout(() => {
              console.log('Reloading room basement data after banner upload...');
              loadRoomBasementData();
            }, 1000);
          }
        });
      }
    });
  }
  
  // Gallery upload
  const galleryInput = document.getElementById('room-basement-gallery-upload');
  const addGalleryBtn = document.getElementById('room-basement-add-gallery-photo');
  
  if (addGalleryBtn) {
    addGalleryBtn.addEventListener('click', () => {
      if (galleryInput) galleryInput.click();
    });
  }
  
  if (galleryInput) {
    galleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-basement-gallery');
      if (!galleryField) return;
      
      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (e) {
        console.error('Failed to parse gallery:', e);
      }
      
      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }
      
      for (const file of files) {
        await uploadBasementGalleryImage(file);
      }
      
      e.target.value = ''; // Reset input
    });
  }

  const commonGalleryInput = document.getElementById('room-basement-common-gallery-upload');
  const addCommonGalleryBtn = document.getElementById('room-basement-add-common-gallery-photo');
  if (addCommonGalleryBtn) {
    addCommonGalleryBtn.addEventListener('click', () => {
      if (commonGalleryInput) commonGalleryInput.click();
    });
  }
  if (commonGalleryInput) {
    commonGalleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-basement-common-gallery');
      if (!galleryField) return;

      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (err) {
        console.error('Failed to parse common gallery:', err);
      }

      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }

      for (const file of files) {
        await uploadBasementCommonGalleryImage(file);
      }

      e.target.value = '';
    });
  }

  ['room-basement-gallery-section-title', 'room-basement-common-gallery-section-title'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    ['input', 'change'].forEach((evt) => {
      el.addEventListener(evt, () => {
        if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
          roomBasementHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomBasementAutoSave === 'function') {
          window.scheduleRoomBasementAutoSave();
        }
      });
    });
  });
}

// Load room ground queen data
// Load room ground queen data (text, banner, gallery)
async function loadRoomGroundQueenData() {
  console.log('Loading room ground queen page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        const titleField = document.getElementById('room-ground-queen-title');
        const subtitleField = document.getElementById('room-ground-queen-subtitle');
        const titlePreview = document.getElementById('preview-room-ground-queen-title');
        const subtitlePreview = document.getElementById('preview-room-ground-queen-subtitle');
        if (titleField) titleField.value = data.roomGroundQueenTitle || '';
        if (subtitleField) subtitleField.value = data.roomGroundQueenSubtitle || '';
        if (titlePreview) titlePreview.textContent = data.roomGroundQueenTitle || 'The Nouk';
        if (subtitlePreview) subtitlePreview.textContent = data.roomGroundQueenSubtitle || 'Bright room near the living room with fireplace. Ideal for two.';
        
        // Banner image
        const bannerImg = document.getElementById('preview-room-ground-queen-banner-img');
        const bannerImageUrlField = document.getElementById('room-ground-queen-banner-image-url');
        const bannerImageUrl = result.data.roomGroundQueenBannerImageUrl || '';
        console.log('Loading banner from API:', bannerImageUrl);
        if (bannerImageUrlField) {
          bannerImageUrlField.value = bannerImageUrl;
          console.log('Banner URL field updated:', bannerImageUrl);
        }
        if (bannerImg && bannerImageUrl) {
          bannerImg.src = bannerImageUrl + '?v=' + Date.now();
          bannerImg.style.display = 'block';
          const span = bannerImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
          console.log('Banner preview updated from API');
        } else if (bannerImg && !bannerImageUrl) {
          console.log('No banner URL in API, keeping default');
        }
        
        // Gallery
        let gallery = [];
        try {
          gallery = JSON.parse(data.roomGroundQueenGallery || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        updateRoomGroundQueenGalleryPreview(gallery);
        const galleryField = document.getElementById('room-ground-queen-gallery');
        if (galleryField) galleryField.value = JSON.stringify(gallery);

        const roomGalTitleEl = document.getElementById('room-ground-queen-gallery-section-title');
        const commonGalTitleEl = document.getElementById('room-ground-queen-common-gallery-section-title');
        if (roomGalTitleEl) {
          roomGalTitleEl.value =
            data.roomGroundQueenGallerySectionTitle != null &&
            String(data.roomGroundQueenGallerySectionTitle).trim() !== ''
              ? String(data.roomGroundQueenGallerySectionTitle)
              : 'Room photos';
        }
        if (commonGalTitleEl) {
          commonGalTitleEl.value =
            data.roomGroundQueenCommonGallerySectionTitle != null &&
            String(data.roomGroundQueenCommonGallerySectionTitle).trim() !== ''
              ? String(data.roomGroundQueenCommonGallerySectionTitle)
              : 'Common areas photos';
        }
        let commonGallery = [];
        try {
          commonGallery = JSON.parse(data.roomGroundQueenCommonGallery || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }
        updateRoomGroundQueenCommonGalleryPreview(commonGallery);
        const commonGalleryField = document.getElementById('room-ground-queen-common-gallery');
        if (commonGalleryField) commonGalleryField.value = JSON.stringify(commonGallery);
        
        // Booking card
        const capacityField = document.getElementById('room-ground-queen-capacity');
        const descField = document.getElementById('room-ground-queen-description');
        const noteField = document.getElementById('room-ground-queen-note');
        const capacityPreview = document.getElementById('preview-room-ground-queen-capacity');
        const descPreview = document.getElementById('preview-room-ground-queen-desc');
        const notePreview = document.getElementById('preview-room-ground-queen-note');
        if (descField) descField.value = data.roomGroundQueenDescription || '';
        if (noteField) noteField.value = data.roomGroundQueenNote || '';
        applyRoomPriceTripletFromApi(data, 'ground_queen');
        applyRoomBookingLineFromApi('capacity', data.roomGroundQueenCapacity, capacityField, capacityPreview, 'up to 2 guests');
        if (descPreview) {
          const desc = data.roomGroundQueenDescription || 'A small but bright room with a large double bed. A shared bathroom with a spacious bathtub is located nearby. The location of the room makes it a perfect spot for socializing and relaxing in the house\'s common areas. With convenient access to the living room with a fireplace, guests can unwind by the fire and connect with others.';
          // Replace newlines with <br> for display in contenteditable
          descPreview.textContent = desc;
        }
        if (notePreview) notePreview.textContent = data.roomGroundQueenNote || '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.';
      }
    }
  } catch (error) {
    console.log('Failed to load room ground queen page data:', error);
  }
}

// Update gallery preview
function updateRoomGroundQueenGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-ground-queen-gallery-preview');
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceGroundQueenGalleryImage(index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteGroundQueenGalleryImage(index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if below max (room pages)
  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-ground-queen-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

function updateRoomGroundQueenCommonGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-ground-queen-common-gallery-preview');
  if (!galleryPreview) return;

  galleryPreview.innerHTML = '';

  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText =
      'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';

    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText =
      'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceGroundQueenCommonGalleryImage(index);
    };

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText =
      'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteGroundQueenCommonGalleryImage(index);
    };

    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });

  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText =
      'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-ground-queen-common-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

window.replaceGroundQueenCommonGalleryImage = function (index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadGroundQueenCommonGalleryImage(file, index);
    }
  };
  input.click();
};

window.deleteGroundQueenCommonGalleryImage = function (index) {
  const galleryField = document.getElementById('room-ground-queen-common-gallery');
  if (!galleryField) return;

  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse common gallery:', e);
    return;
  }

  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomGroundQueenCommonGalleryPreview(gallery);

  if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
    if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
      roomGroundQueenHasUnsavedChanges = true;
    }
    window.scheduleRoomGroundQueenAutoSave();
  }
};

async function uploadGroundQueenCommonGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-ground-queen-common-gallery');
    formData.append('image', file);

    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl
        ? payload.imageUrl
        : payload && payload.filepath
          ? payload.filepath
          : result.imageUrl || result.filepath || '';
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-ground-queen-common-gallery');
        if (!galleryField) return;

        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }

        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
          gallery.push(imageUrl);
        } else {
          alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
          return;
        }

        galleryField.value = JSON.stringify(gallery);
        updateRoomGroundQueenCommonGalleryPreview(gallery);

        if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
          if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
            roomGroundQueenHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundQueenAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading common gallery image:', error);
  }
}

// Replace gallery image
window.replaceGroundQueenGalleryImage = function(index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadGroundQueenGalleryImage(file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteGroundQueenGalleryImage = function(index) {
  const galleryField = document.getElementById('room-ground-queen-gallery');
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomGroundQueenGalleryPreview(gallery);
  
  if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
    if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
      roomGroundQueenHasUnsavedChanges = true;
    }
    window.scheduleRoomGroundQueenAutoSave();
  }
};

// Upload gallery image
async function uploadGroundQueenGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-ground-queen-gallery');
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      // Extract imageUrl from response (can be in result.data or result directly)
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log('Gallery image upload result:', result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-ground-queen-gallery');
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
            gallery.push(imageUrl);
          } else {
            alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateRoomGroundQueenGalleryPreview(gallery);
        
        if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
          if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
            roomGroundQueenHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundQueenAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading gallery image:', error);
  }
}

// Initialize room ground queen image upload
function initRoomGroundQueenImageUpload() {
  // Banner upload
  const bannerInput = document.getElementById('room-ground-queen-banner-upload');
  if (bannerInput) {
    bannerInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        const bannerImg = document.getElementById('preview-room-ground-queen-banner-img');
        await uploadImage(file, 'ground-queen-banner', null, null, {
          localStorageKey: 'btb_room_ground_queen',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomGroundQueenData,
          imageNameMapper: () => 'Ground Queen Banner',
          onSuccess: (imageUrl) => {
            console.log('Banner uploaded successfully, URL:', imageUrl);
            const bannerImageUrlField = document.getElementById('room-ground-queen-banner-image-url');
            if (bannerImageUrlField) {
              bannerImageUrlField.value = imageUrl;
              console.log('Banner URL saved to hidden field:', imageUrl);
            }
            if (bannerImg) {
              bannerImg.src = imageUrl + '?v=' + Date.now();
              bannerImg.style.display = 'block';
              const span = bannerImg.parentElement.querySelector('span');
              if (span) span.style.display = 'none';
              console.log('Banner preview updated in admin');
            }
            // Banner is saved automatically by upload_image.php to room_ground_queen_banner_image_url
            // Reload data to ensure consistency
            setTimeout(() => {
              console.log('Reloading room ground queen data after banner upload...');
              loadRoomGroundQueenData();
            }, 1000);
          }
        });
      }
    });
  }
  
  // Gallery upload
  const galleryInput = document.getElementById('room-ground-queen-gallery-upload');
  const addGalleryBtn = document.getElementById('room-ground-queen-add-gallery-photo');
  
  if (addGalleryBtn) {
    addGalleryBtn.addEventListener('click', () => {
      if (galleryInput) galleryInput.click();
    });
  }
  
  if (galleryInput) {
    galleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-ground-queen-gallery');
      if (!galleryField) return;
      
      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (e) {
        console.error('Failed to parse gallery:', e);
      }
      
      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }
      
      for (const file of files) {
        await uploadGroundQueenGalleryImage(file);
      }
      
      e.target.value = ''; // Reset input
    });
  }

  const commonGalleryInput = document.getElementById('room-ground-queen-common-gallery-upload');
  const addCommonGalleryBtn = document.getElementById('room-ground-queen-add-common-gallery-photo');
  if (addCommonGalleryBtn) {
    addCommonGalleryBtn.addEventListener('click', () => {
      if (commonGalleryInput) commonGalleryInput.click();
    });
  }
  if (commonGalleryInput) {
    commonGalleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-ground-queen-common-gallery');
      if (!galleryField) return;

      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (err) {
        console.error('Failed to parse common gallery:', err);
      }

      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }

      for (const file of files) {
        await uploadGroundQueenCommonGalleryImage(file);
      }

      e.target.value = '';
    });
  }

  ['room-ground-queen-gallery-section-title', 'room-ground-queen-common-gallery-section-title'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    ['input', 'change'].forEach((evt) => {
      el.addEventListener(evt, () => {
        if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
          roomGroundQueenHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
          window.scheduleRoomGroundQueenAutoSave();
        }
      });
    });
  });
}

// Load room ground twin data (text, banner, gallery)
async function loadRoomGroundTwinData() {
  console.log('Loading room ground twin page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        const titleField = document.getElementById('room-ground-twin-title');
        const subtitleField = document.getElementById('room-ground-twin-subtitle');
        const titlePreview = document.getElementById('preview-room-ground-twin-title');
        const subtitlePreview = document.getElementById('preview-room-ground-twin-subtitle');
        if (titleField) titleField.value = data.roomGroundTwinTitle || '';
        if (subtitleField) subtitleField.value = data.roomGroundTwinSubtitle || '';
        if (titlePreview) titlePreview.textContent = data.roomGroundTwinTitle || 'Vrienden';
        if (subtitlePreview) subtitlePreview.textContent = data.roomGroundTwinSubtitle || 'Great for friends or colleagues. Close to the kitchen and massage hall.';
        
        // Banner image
        const bannerImg = document.getElementById('preview-room-ground-twin-banner-img');
        const bannerImageUrlField = document.getElementById('room-ground-twin-banner-image-url');
        const bannerImageUrl = result.data.roomGroundTwinBannerImageUrl || '';
        console.log('Loading banner from API:', bannerImageUrl);
        if (bannerImageUrlField) {
          bannerImageUrlField.value = bannerImageUrl;
          console.log('Banner URL field updated:', bannerImageUrl);
        }
        if (bannerImg && bannerImageUrl) {
          bannerImg.src = bannerImageUrl + '?v=' + Date.now();
          bannerImg.style.display = 'block';
          const span = bannerImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
          console.log('Banner preview updated from API');
        } else if (bannerImg && !bannerImageUrl) {
          console.log('No banner URL in API, keeping default');
        }
        
        // Gallery
        let gallery = [];
        try {
          gallery = JSON.parse(data.roomGroundTwinGallery || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        updateRoomGroundTwinGalleryPreview(gallery);
        const galleryField = document.getElementById('room-ground-twin-gallery');
        if (galleryField) galleryField.value = JSON.stringify(gallery);

        const roomGalTitleEl = document.getElementById('room-ground-twin-gallery-section-title');
        const commonGalTitleEl = document.getElementById('room-ground-twin-common-gallery-section-title');
        if (roomGalTitleEl) {
          roomGalTitleEl.value =
            data.roomGroundTwinGallerySectionTitle != null &&
            String(data.roomGroundTwinGallerySectionTitle).trim() !== ''
              ? String(data.roomGroundTwinGallerySectionTitle)
              : 'Room photos';
        }
        if (commonGalTitleEl) {
          commonGalTitleEl.value =
            data.roomGroundTwinCommonGallerySectionTitle != null &&
            String(data.roomGroundTwinCommonGallerySectionTitle).trim() !== ''
              ? String(data.roomGroundTwinCommonGallerySectionTitle)
              : 'Common areas photos';
        }
        let commonGallery = [];
        try {
          commonGallery = JSON.parse(data.roomGroundTwinCommonGallery || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }
        updateRoomGroundTwinCommonGalleryPreview(commonGallery);
        const commonGalleryField = document.getElementById('room-ground-twin-common-gallery');
        if (commonGalleryField) commonGalleryField.value = JSON.stringify(commonGallery);
        
        // Booking card
        const capacityField = document.getElementById('room-ground-twin-capacity');
        const descField = document.getElementById('room-ground-twin-description');
        const noteField = document.getElementById('room-ground-twin-note');
        const capacityPreview = document.getElementById('preview-room-ground-twin-capacity');
        const descPreview = document.getElementById('preview-room-ground-twin-desc');
        const notePreview = document.getElementById('preview-room-ground-twin-note');
        if (descField) descField.value = data.roomGroundTwinDescription || '';
        if (noteField) noteField.value = data.roomGroundTwinNote || '';
        applyRoomPriceTripletFromApi(data, 'ground_twin');
        applyRoomBookingLineFromApi('capacity', data.roomGroundTwinCapacity, capacityField, capacityPreview, 'up to 2 guests');
        if (descPreview) {
          const desc = data.roomGroundTwinDescription || 'A comfortable room with two twin beds, perfect for friends or colleagues traveling together. The room is located on the ground floor, close to the shared kitchen and massage hall, making it convenient for guests who want to socialize or use the common areas.';
          // Replace newlines with <br> for display in contenteditable
          descPreview.textContent = desc;
        }
        if (notePreview) notePreview.textContent = data.roomGroundTwinNote || '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.';
      }
    }
  } catch (error) {
    console.log('Failed to load room ground twin page data:', error);
  }
}

// Update gallery preview
function updateRoomGroundTwinGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-ground-twin-gallery-preview');
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceGroundTwinGalleryImage(index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteGroundTwinGalleryImage(index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if below max (room pages)
  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-ground-twin-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

function updateRoomGroundTwinCommonGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-ground-twin-common-gallery-preview');
  if (!galleryPreview) return;

  galleryPreview.innerHTML = '';

  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText =
      'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';

    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText =
      'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceGroundTwinCommonGalleryImage(index);
    };

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText =
      'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteGroundTwinCommonGalleryImage(index);
    };

    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });

  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText =
      'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-ground-twin-common-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

window.replaceGroundTwinCommonGalleryImage = function (index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadGroundTwinCommonGalleryImage(file, index);
    }
  };
  input.click();
};

window.deleteGroundTwinCommonGalleryImage = function (index) {
  const galleryField = document.getElementById('room-ground-twin-common-gallery');
  if (!galleryField) return;

  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse common gallery:', e);
    return;
  }

  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomGroundTwinCommonGalleryPreview(gallery);

  if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
    if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
      roomGroundTwinHasUnsavedChanges = true;
    }
    window.scheduleRoomGroundTwinAutoSave();
  }
};

async function uploadGroundTwinCommonGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-ground-twin-common-gallery');
    formData.append('image', file);

    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl
        ? payload.imageUrl
        : payload && payload.filepath
          ? payload.filepath
          : result.imageUrl || result.filepath || '';
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-ground-twin-common-gallery');
        if (!galleryField) return;

        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }

        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
          gallery.push(imageUrl);
        } else {
          alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
          return;
        }

        galleryField.value = JSON.stringify(gallery);
        updateRoomGroundTwinCommonGalleryPreview(gallery);

        if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
          if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
            roomGroundTwinHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundTwinAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading common gallery image:', error);
  }
}

// Replace gallery image
window.replaceGroundTwinGalleryImage = function(index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadGroundTwinGalleryImage(file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteGroundTwinGalleryImage = function(index) {
  const galleryField = document.getElementById('room-ground-twin-gallery');
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomGroundTwinGalleryPreview(gallery);
  
  if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
    if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
      roomGroundTwinHasUnsavedChanges = true;
    }
    window.scheduleRoomGroundTwinAutoSave();
  }
};

// Upload gallery image
async function uploadGroundTwinGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-ground-twin-gallery');
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      // Extract imageUrl from response (can be in result.data or result directly)
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log('Gallery image upload result:', result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-ground-twin-gallery');
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
            gallery.push(imageUrl);
          } else {
            alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateRoomGroundTwinGalleryPreview(gallery);
        
        if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
          if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
            roomGroundTwinHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundTwinAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading gallery image:', error);
  }
}

// Initialize room ground twin image upload
function initRoomGroundTwinImageUpload() {
  // Banner upload
  const bannerInput = document.getElementById('room-ground-twin-banner-upload');
  if (bannerInput) {
    bannerInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        const bannerImg = document.getElementById('preview-room-ground-twin-banner-img');
        await uploadImage(file, 'ground-twin-banner', null, null, {
          localStorageKey: 'btb_room_ground_twin',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomGroundTwinData,
          imageNameMapper: () => 'Ground Twin Banner',
          onSuccess: (imageUrl) => {
            console.log('Banner uploaded successfully, URL:', imageUrl);
            const bannerImageUrlField = document.getElementById('room-ground-twin-banner-image-url');
            if (bannerImageUrlField) {
              bannerImageUrlField.value = imageUrl;
              console.log('Banner URL saved to hidden field:', imageUrl);
            }
            if (bannerImg) {
              bannerImg.src = imageUrl + '?v=' + Date.now();
              bannerImg.style.display = 'block';
              const span = bannerImg.parentElement.querySelector('span');
              if (span) span.style.display = 'none';
              console.log('Banner preview updated in admin');
            }
            // Banner is saved automatically by upload_image.php to room_ground_twin_banner_image_url
            // Reload data to ensure consistency
            setTimeout(() => {
              console.log('Reloading room ground twin data after banner upload...');
              loadRoomGroundTwinData();
            }, 1000);
          }
        });
      }
    });
  }
  
  // Gallery upload
  const galleryInput = document.getElementById('room-ground-twin-gallery-upload');
  const addGalleryBtn = document.getElementById('room-ground-twin-add-gallery-photo');
  
  if (addGalleryBtn) {
    addGalleryBtn.addEventListener('click', () => {
      if (galleryInput) galleryInput.click();
    });
  }
  
  if (galleryInput) {
    galleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-ground-twin-gallery');
      if (!galleryField) return;
      
      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (e) {
        console.error('Failed to parse gallery:', e);
      }
      
      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }
      
      for (const file of files) {
        await uploadGroundTwinGalleryImage(file);
      }
      
      e.target.value = ''; // Reset input
    });
  }

  const commonGalleryInput = document.getElementById('room-ground-twin-common-gallery-upload');
  const addCommonGalleryBtn = document.getElementById('room-ground-twin-add-common-gallery-photo');
  if (addCommonGalleryBtn) {
    addCommonGalleryBtn.addEventListener('click', () => {
      if (commonGalleryInput) commonGalleryInput.click();
    });
  }
  if (commonGalleryInput) {
    commonGalleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-ground-twin-common-gallery');
      if (!galleryField) return;

      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (err) {
        console.error('Failed to parse common gallery:', err);
      }

      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }

      for (const file of files) {
        await uploadGroundTwinCommonGalleryImage(file);
      }

      e.target.value = '';
    });
  }

  ['room-ground-twin-gallery-section-title', 'room-ground-twin-common-gallery-section-title'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    ['input', 'change'].forEach((evt) => {
      el.addEventListener(evt, () => {
        if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
          roomGroundTwinHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
          window.scheduleRoomGroundTwinAutoSave();
        }
      });
    });
  });
}

// Load room second data (text, banner, gallery)
async function loadRoomSecondData() {
  console.log('Loading room second page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section
        const titleField = document.getElementById('room-second-title');
        const subtitleField = document.getElementById('room-second-subtitle');
        const titlePreview = document.getElementById('preview-room-second-title');
        const subtitlePreview = document.getElementById('preview-room-second-subtitle');
        if (titleField) titleField.value = data.roomSecondTitle || '';
        if (subtitleField) subtitleField.value = data.roomSecondSubtitle || '';
        if (titlePreview) titlePreview.textContent = data.roomSecondTitle || 'Kelder';
        if (subtitlePreview) subtitlePreview.textContent = data.roomSecondSubtitle || 'A private loft under the roof: bedroom, kitchenette, shower, study and balcony.';
        
        // Banner image
        const bannerImg = document.getElementById('preview-room-second-banner-img');
        const bannerImageUrlField = document.getElementById('room-second-banner-image-url');
        const bannerImageUrl = result.data.roomSecondBannerImageUrl || '';
        console.log('Loading banner from API:', bannerImageUrl);
        if (bannerImageUrlField) {
          bannerImageUrlField.value = bannerImageUrl;
          console.log('Banner URL field updated:', bannerImageUrl);
        }
        if (bannerImg && bannerImageUrl) {
          bannerImg.src = bannerImageUrl + '?v=' + Date.now();
          bannerImg.style.display = 'block';
          const span = bannerImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
          console.log('Banner preview updated from API');
        } else if (bannerImg && !bannerImageUrl) {
          console.log('No banner URL in API, keeping default');
        }
        
        // Gallery
        let gallery = [];
        try {
          gallery = JSON.parse(data.roomSecondGallery || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        updateRoomSecondGalleryPreview(gallery);
        const galleryField = document.getElementById('room-second-gallery');
        if (galleryField) galleryField.value = JSON.stringify(gallery);

        const roomGalTitleEl = document.getElementById('room-second-gallery-section-title');
        const commonGalTitleEl = document.getElementById('room-second-common-gallery-section-title');
        if (roomGalTitleEl) {
          roomGalTitleEl.value =
            data.roomSecondGallerySectionTitle != null && String(data.roomSecondGallerySectionTitle).trim() !== ''
              ? String(data.roomSecondGallerySectionTitle)
              : 'Room photos';
        }
        if (commonGalTitleEl) {
          commonGalTitleEl.value =
            data.roomSecondCommonGallerySectionTitle != null &&
            String(data.roomSecondCommonGallerySectionTitle).trim() !== ''
              ? String(data.roomSecondCommonGallerySectionTitle)
              : 'Common areas photos';
        }
        let commonGallery = [];
        try {
          commonGallery = JSON.parse(data.roomSecondCommonGallery || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }
        updateRoomSecondCommonGalleryPreview(commonGallery);
        const commonGalleryField = document.getElementById('room-second-common-gallery');
        if (commonGalleryField) commonGalleryField.value = JSON.stringify(commonGallery);
        
        // Booking card
        const capacityField = document.getElementById('room-second-capacity');
        const descField = document.getElementById('room-second-description');
        const noteField = document.getElementById('room-second-note');
        const capacityPreview = document.getElementById('preview-room-second-capacity');
        const descPreview = document.getElementById('preview-room-second-desc');
        const notePreview = document.getElementById('preview-room-second-note');
        if (descField) descField.value = data.roomSecondDescription || '';
        if (noteField) noteField.value = data.roomSecondNote || '';
        applyRoomPriceTripletFromApi(data, 'second');
        applyRoomBookingLineFromApi('capacity', data.roomSecondCapacity, capacityField, capacityPreview, 'up to 2 guests');
        if (descPreview) {
          const desc = data.roomSecondDescription || 'A fully private floor featuring a large living area with a king-size bed, a separate kitchen, a private bathroom with a shower, a bright workspace, and a spacious balcony with stunning views of the lake and mountains.';
          // Replace newlines with <br> for display in contenteditable
          descPreview.textContent = desc;
        }
        if (notePreview) notePreview.textContent = data.roomSecondNote || '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.';
      }
    }
  } catch (error) {
    console.log('Failed to load room second page data:', error);
  }
}

// Update gallery preview
function updateRoomSecondGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-second-gallery-preview');
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceGalleryImage(index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteGalleryImage(index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if below max (room pages)
  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-second-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

function updateRoomSecondCommonGalleryPreview(gallery) {
  const galleryPreview = document.getElementById('room-second-common-gallery-preview');
  if (!galleryPreview) return;

  galleryPreview.innerHTML = '';

  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText =
      'position: relative; width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';

    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText =
      'position: absolute; top: 4px; left: 4px; padding: 4px 8px; font-size: 0.75rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceRoomSecondCommonGalleryImage(index);
    };

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText =
      'position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; padding: 0; font-size: 1.2rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteRoomSecondCommonGalleryImage(index);
    };

    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });

  if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
    const addItem = document.createElement('div');
    addItem.style.cssText =
      'width: 120px; height: 120px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 2rem;">+</span>';
    addItem.onclick = () => document.getElementById('room-second-common-gallery-upload').click();
    galleryPreview.appendChild(addItem);
  }
}

window.replaceRoomSecondCommonGalleryImage = function (index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadRoomSecondCommonGalleryImage(file, index);
    }
  };
  input.click();
};

window.deleteRoomSecondCommonGalleryImage = function (index) {
  const galleryField = document.getElementById('room-second-common-gallery');
  if (!galleryField) return;

  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse common gallery:', e);
    return;
  }

  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomSecondCommonGalleryPreview(gallery);

  if (typeof window.scheduleRoomSecondAutoSave === 'function') {
    if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
      roomSecondHasUnsavedChanges = true;
    }
    window.scheduleRoomSecondAutoSave();
  }
};

async function uploadRoomSecondCommonGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-second-common-gallery');
    formData.append('image', file);

    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl
        ? payload.imageUrl
        : payload && payload.filepath
          ? payload.filepath
          : result.imageUrl || result.filepath || '';
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-second-common-gallery');
        if (!galleryField) return;

        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse common gallery:', e);
        }

        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
          gallery.push(imageUrl);
        } else {
          alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
          return;
        }

        galleryField.value = JSON.stringify(gallery);
        updateRoomSecondCommonGalleryPreview(gallery);

        if (typeof window.scheduleRoomSecondAutoSave === 'function') {
          if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
            roomSecondHasUnsavedChanges = true;
          }
          window.scheduleRoomSecondAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading common gallery image:', error);
  }
}

// Replace gallery image
window.replaceGalleryImage = function(index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadGalleryImage(file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteGalleryImage = function(index) {
  const galleryField = document.getElementById('room-second-gallery');
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRoomSecondGalleryPreview(gallery);
  
  if (typeof window.scheduleRoomSecondAutoSave === 'function') {
    if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
      roomSecondHasUnsavedChanges = true;
    }
    window.scheduleRoomSecondAutoSave();
  }
};

// Upload gallery image
async function uploadGalleryImage(file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', 'room-second-gallery');
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      // Extract imageUrl from response (can be in result.data or result directly)
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log('Gallery image upload result:', result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById('room-second-gallery');
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < ROOM_PAGE_GALLERY_MAX_PHOTOS) {
            gallery.push(imageUrl);
          } else {
            alert(`Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed in gallery`);
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateRoomSecondGalleryPreview(gallery);
        
        if (typeof window.scheduleRoomSecondAutoSave === 'function') {
          if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
            roomSecondHasUnsavedChanges = true;
          }
          window.scheduleRoomSecondAutoSave();
        }
      }
    }
  } catch (error) {
    console.error('Error uploading gallery image:', error);
  }
}

// Initialize room second image upload
function initRoomSecondImageUpload() {
  // Banner upload
  const bannerInput = document.getElementById('room-second-banner-upload');
  if (bannerInput) {
    bannerInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        const bannerImg = document.getElementById('preview-room-second-banner-img');
        await uploadImage(file, 'second-banner', null, null, {
          localStorageKey: 'btb_room_second',
          fieldNameMapper: () => 'bannerImageUrl',
          reloadFunction: loadRoomSecondData,
          imageNameMapper: () => 'Second Floor Banner',
          onSuccess: (imageUrl) => {
            const bannerImageUrlField = document.getElementById('room-second-banner-image-url');
            if (bannerImageUrlField) bannerImageUrlField.value = imageUrl;
            if (bannerImg) {
              bannerImg.src = imageUrl + '?v=' + Date.now();
              bannerImg.style.display = 'block';
              bannerImg.parentElement.querySelector('span').style.display = 'none';
            }
            // Banner is saved automatically by upload_image.php, but we can trigger a reload to ensure consistency
            setTimeout(() => {
              loadRoomSecondData();
            }, 500);
          }
        });
      }
    });
  }
  
  // Gallery upload
  const galleryInput = document.getElementById('room-second-gallery-upload');
  const addGalleryBtn = document.getElementById('room-second-add-gallery-photo');
  
  if (addGalleryBtn) {
    addGalleryBtn.addEventListener('click', () => {
      if (galleryInput) galleryInput.click();
    });
  }
  
  if (galleryInput) {
    galleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-second-gallery');
      if (!galleryField) return;
      
      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (e) {
        console.error('Failed to parse gallery:', e);
      }
      
      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }
      
      for (const file of files) {
        await uploadGalleryImage(file);
      }
      
      e.target.value = ''; // Reset input
    });
  }

  const commonGalleryInput = document.getElementById('room-second-common-gallery-upload');
  const addCommonGalleryBtn = document.getElementById('room-second-add-common-gallery-photo');
  if (addCommonGalleryBtn) {
    addCommonGalleryBtn.addEventListener('click', () => {
      if (commonGalleryInput) commonGalleryInput.click();
    });
  }
  if (commonGalleryInput) {
    commonGalleryInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      const galleryField = document.getElementById('room-second-common-gallery');
      if (!galleryField) return;

      let gallery = [];
      try {
        gallery = JSON.parse(galleryField.value || '[]');
      } catch (err) {
        console.error('Failed to parse common gallery:', err);
      }

      const remainingSlots = ROOM_PAGE_GALLERY_MAX_PHOTOS - gallery.length;
      if (files.length > remainingSlots) {
        alert(`You can only add ${remainingSlots} more photo(s). Maximum ${ROOM_PAGE_GALLERY_MAX_PHOTOS} photos allowed.`);
        files.splice(remainingSlots);
      }

      for (const file of files) {
        await uploadRoomSecondCommonGalleryImage(file);
      }

      e.target.value = '';
    });
  }

  ['room-second-gallery-section-title', 'room-second-common-gallery-section-title'].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    ['input', 'change'].forEach((evt) => {
      el.addEventListener(evt, () => {
        if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
          roomSecondHasUnsavedChanges = true;
        }
        if (typeof window.scheduleRoomSecondAutoSave === 'function') {
          window.scheduleRoomSecondAutoSave();
        }
      });
    });
  });
}

// ==========================================
// PAGE CONTENT MANAGEMENT
// ==========================================

const retreatDefaultValues = {
  'retreat-hero-title': 'Activities and Practices at Back to Base',
  'retreat-hero-subtitle': 'Where nature and quiet become part of your practice',
  'retreat-locations-title': 'Our locations for your workshops',
  'retreat-forest-title': 'Outdoor space with multi functional platforms',
  'retreat-forest-description': 'Just a few steps from the house, a winding path leads into the forest, where wooden platforms are hidden among tall trees. The air feels lighter here, the sound of the creek creates a natural meditation, and the soft light filtering through the canopy makes every practice deeper.\n\nIt\'s an ideal spot for:\nSunrise yoga\nEvening meditations\nBreathwork\nAny activity that benefits from a strong connection to nature',
  'retreat-indoor-title': 'Multifunctional indoor space',
  'retreat-indoor-description': 'Inside the house, there is a spacious room with large windows filled with light, warmth, and a sense of comfort — perfect for group gatherings, mini-lectures, workshops, breathwork sessions, or yoga during cooler weather.\n\nAnd if you need a more intimate atmosphere or plan to use visual materials, the room can easily be darkened with blackout curtains.',
  'retreat-theatre-title': 'Cozy mini home theatre',
  'retreat-theatre-description': 'For presentations, educational films, documentaries, or shared viewing sessions, we offer a small but very cozy home theatre. Soft lighting, quality sound, and a calm environment help create a fully immersive experience.',
  'retreat-contact-title': 'Are you looking for a place to retreat or interested in joining a workshop?',
  'retreat-contact-text': 'Just send us a message with your preferences, and we will create a program tailored specifically for you!',
  'retreat-collaboration-title': 'Invitation to Collaborate',
  'retreat-collaboration-intro': 'Back to Base welcomes those who create transformative practices and help people heal and restore.\nWe are looking for:\n\nProgram creators\nYoga instructors\nMeditation teachers\nMassage therapists\nReiki practitioners\nAcupuncturists\nBody-oriented specialists\n\nIf you want to share your work in the quiet of the forest beside a mountain lake, we would be happy to collaborate with you.\nJust call or message us!'
};

const retreatFieldChecklist = [
  { id: 'retreat-hero-title', label: 'Hero Section — title' },
  { id: 'retreat-hero-subtitle', label: 'Hero Section — subtitle' },
  { id: 'retreat-forest-description', label: 'Outdoor space card — description' },
  { id: 'retreat-indoor-description', label: 'Multifunctional indoor space card — description' },
  { id: 'retreat-theatre-description', label: 'Home Theatre Card — description' },
  { id: 'retreat-contact-text', label: 'Contact form — body text' },
  { id: 'retreat-collaboration-intro', label: 'Invitation to Collaborate (side image) — introduction' }
];

let retreatHelperInitialized = false;

function getRetreatFieldValue(id) {
  const el = document.getElementById(id);
  if (!el) return '';
  return Object.prototype.hasOwnProperty.call(el, 'value') ? (el.value || '') : (el.textContent || '');
}

function setRetreatFieldValue(el, value) {
  if (!el) return;
  if (Object.prototype.hasOwnProperty.call(el, 'value')) {
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  } else {
    el.textContent = value;
  }
}

function applyRetreatDefaults(options = {}) {
  const onlyEmpty = options.onlyEmpty !== false;
  let updated = 0;
  Object.entries(retreatDefaultValues).forEach(([id, defaultValue]) => {
    const el = document.getElementById(id);
    if (!el) return;
    const current = getRetreatFieldValue(id).trim();
    if (onlyEmpty && current) {
      return;
    }
    setRetreatFieldValue(el, defaultValue);
    updated++;
  });
  updateRetreatContentStatus();
  return updated;
}

function updateRetreatContentStatus() {
  const statusEl = document.getElementById('retreat-content-status');
  if (!statusEl) return;
  const missing = retreatFieldChecklist.filter(field => !getRetreatFieldValue(field.id).trim());
  if (!missing.length) {
    statusEl.textContent = 'All key sections look complete';
    statusEl.className = 'status-badge success';
    return;
  }
  const preview = missing.slice(0, 3).map(field => field.label).join(', ');
  const remainder = missing.length > 3 ? ` +${missing.length - 3}` : '';
  statusEl.textContent = `Missing text: ${preview}${remainder}`;
  statusEl.className = 'status-badge warning';
}

function initRetreatHelperUI() {
  if (retreatHelperInitialized) {
    updateRetreatContentStatus();
    return;
  }
  const section = document.getElementById('retreat-workshop-section');
  if (!section) return;
  retreatHelperInitialized = true;
  
  const fillBtn = document.getElementById('retreat-fill-missing');
  if (!fillBtn) {
    retreatHelperInitialized = true;
    updateRetreatContentStatus();
    return;
  }

  if (fillBtn) {
    fillBtn.addEventListener('click', (event) => {
      const fillAll = event.shiftKey;
      if (fillAll) {
        const confirmed = window.confirm('Replace all Retreats and Workshops texts with the default story? This cannot be undone.');
        if (!confirmed) return;
      }
      const updated = applyRetreatDefaults({ onlyEmpty: !fillAll });
      if (typeof showStatus === 'function') {
        if (updated === 0) {
          showStatus(fillAll ? 'Default copy already applied to every field.' : 'All fields already contain text.');
        } else if (fillAll) {
          showStatus('Retreat copy reset to the default story for every section.');
        } else {
          showStatus(`Filled ${updated} empty field${updated === 1 ? '' : 's'} with the default story.`);
        }
      }
    });
  } else {
    updateRetreatContentStatus();
  }
  
  section.querySelectorAll('input, textarea').forEach(input => {
    input.addEventListener('input', updateRetreatContentStatus);
  });
  
  updateRetreatContentStatus();
}

/** DB / legacy scripts sometimes store HTML line breaks as literal "<br>" — use real newlines in textareas */
function normalizeRetreatLegacyBr(s) {
  if (s == null || typeof s !== 'string') return '';
  return s.replace(/<br\s*\/?>/gi, '\n');
}

/** Merge legacy list fields into one outdoor description for editor + save. */
function mergeLegacyRetreatForestDescription(data) {
  const n = normalizeRetreatLegacyBr;
  let desc = n(data.retreatForestDescription || '').trim();
  const label = n(data.retreatForestListLabel || '').trim();
  const itemsRaw = n(data.retreatForestListItems || '').trim();
  if (itemsRaw) {
    const lines = itemsRaw.split(/\n/).map((l) => l.trim()).filter(Boolean);
    const block = label ? `${label}\n${lines.join('\n')}` : lines.join('\n');
    desc = desc ? `${desc}\n\n${block}` : block;
  }
  return desc;
}

/** Merge legacy second paragraph into one indoor description for editor + save. */
function mergeLegacyRetreatIndoorDescription(data) {
  const n = normalizeRetreatLegacyBr;
  let desc = n(data.retreatIndoorDescription || '').trim();
  const add = n(data.retreatIndoorAdditional || '').trim();
  if (add) desc = desc ? `${desc}\n\n${add}` : add;
  return desc;
}

/** Merge legacy collaboration list + conclusion into one body for editor + save. */
function mergeLegacyRetreatCollaborationBody(data) {
  const n = normalizeRetreatLegacyBr;
  let body = n(data.retreatCollaborationIntro || '').trim();
  const listRaw = n(data.retreatCollaborationList || '').trim();
  const concl = n(data.retreatCollaborationConclusion || '').trim();
  if (listRaw) {
    const lines = listRaw.split(/\n/).map((l) => l.trim()).filter(Boolean);
    const block = lines.join('\n');
    if (block) body = body ? `${body}\n\n${block}` : block;
  }
  if (concl) body = body ? `${body}\n\n${concl}` : concl;
  return body;
}

// Load retreat and workshop data
async function loadRetreatWorkshopData() {
  console.log('Loading retreat and workshop data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        const n = normalizeRetreatLegacyBr;
        console.log('Retreat data loaded from API:', data);
        console.log('Forest gallery from API:', data.retreatForestGallery);
        console.log('Indoor gallery from API:', data.retreatIndoorGallery);
        console.log('Theatre gallery from API:', data.retreatTheatreGallery);
        
        // Hero section
        document.getElementById('retreat-hero-title').value = data.retreatHeroTitle || '';
        document.getElementById('retreat-hero-subtitle').value = data.retreatHeroSubtitle || '';

        // Invitation to Collaborate (before location cards on site)
        document.getElementById('retreat-collaboration-title').value = data.retreatCollaborationTitle || '';
        document.getElementById('retreat-collaboration-intro').value = n(mergeLegacyRetreatCollaborationBody(data));

        // Schematic preview images (so buildRetreatDataFromFormForPreview sees src after load)
        const setRetreatSchematicImg = (imgId, url) => {
          if (!url || typeof url !== 'string') return;
          const el = document.getElementById(imgId);
          if (!el) return;
          el.src = url + '?v=' + Date.now();
          el.style.display = 'block';
          const span = el.parentElement && el.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
        };
        setRetreatSchematicImg('preview-retreat-hero-img', data.retreatHeroImageUrl);
        setRetreatSchematicImg('preview-retreat-collaboration-img', data.retreatCollaborationImageUrl);
        setRetreatSchematicImg('preview-retreat-forest-img', data.retreatForestImageUrl);
        setRetreatSchematicImg('preview-retreat-indoor-img', data.retreatIndoorImageUrl);
        setRetreatSchematicImg('preview-retreat-theatre-img', data.retreatTheatreImageUrl);
        
        // Locations section
        document.getElementById('retreat-locations-title').value = data.retreatLocationsTitle || '';
        
        // Forest Platforms card (single description; legacy list fields merged on load)
        document.getElementById('retreat-forest-title').value = data.retreatForestTitle || '';
        document.getElementById('retreat-forest-description').value = n(mergeLegacyRetreatForestDescription(data));
        const forestGalleryField = document.getElementById('retreat-forest-gallery');
        if (forestGalleryField) {
          const galleryData = data.retreatForestGallery || '[]';
          console.log('Loading forest gallery from API:', galleryData);
          forestGalleryField.value = galleryData;
          try {
            const gallery = JSON.parse(galleryData);
            console.log('Parsed forest gallery:', gallery);
            updateRetreatLocationGalleryPreview('forest', gallery);
          } catch (e) {
            console.error('Failed to parse forest gallery:', e, 'Raw data:', galleryData);
          }
        } else {
          console.error('Forest gallery field not found!');
        }
        
        // Indoor Space card (single description; legacy additional merged on load)
        document.getElementById('retreat-indoor-title').value = data.retreatIndoorTitle || '';
        document.getElementById('retreat-indoor-description').value = n(mergeLegacyRetreatIndoorDescription(data));
        const indoorGalleryField = document.getElementById('retreat-indoor-gallery');
        if (indoorGalleryField) {
          const galleryData = data.retreatIndoorGallery || '[]';
          console.log('Loading indoor gallery from API:', galleryData);
          indoorGalleryField.value = galleryData;
          try {
            const gallery = JSON.parse(galleryData);
            console.log('Parsed indoor gallery:', gallery);
            updateRetreatLocationGalleryPreview('indoor', gallery);
          } catch (e) {
            console.error('Failed to parse indoor gallery:', e, 'Raw data:', galleryData);
          }
        } else {
          console.error('Indoor gallery field not found!');
        }
        
        // Home Theatre card
        document.getElementById('retreat-theatre-title').value = data.retreatTheatreTitle || '';
        document.getElementById('retreat-theatre-description').value = n(data.retreatTheatreDescription || '');
        const theatreGalleryField = document.getElementById('retreat-theatre-gallery');
        if (theatreGalleryField) {
          const galleryData = data.retreatTheatreGallery || '[]';
          console.log('Loading theatre gallery from API:', galleryData);
          theatreGalleryField.value = galleryData;
          try {
            const gallery = JSON.parse(galleryData);
            console.log('Parsed theatre gallery:', gallery);
            updateRetreatLocationGalleryPreview('theatre', gallery);
          } catch (e) {
            console.error('Failed to parse theatre gallery:', e, 'Raw data:', galleryData);
          }
        } else {
          console.error('Theatre gallery field not found!');
        }
        
        // Contact Form section
        document.getElementById('retreat-contact-title').value = data.retreatContactTitle || '';
        document.getElementById('retreat-contact-text').value = n(data.retreatContactText || '');
        
        // Preview from normalized form fields (API data may still contain literal <br> in strings)
        updateRetreatPreview(buildRetreatDataFromFormForPreview());
        updateRetreatContentStatus();
        
        // Reset unsaved changes flag after loading
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = false;
        }
        if (typeof updateRetreatSaveStatus === 'function') {
          updateRetreatSaveStatus('', '');
        }
        
        console.log('Retreat and workshop content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load retreat and workshop data:', error);
  }
}

/** Build the same shape as API get_content for updateRetreatPreview — from hidden fields only (no fetch). */
function buildRetreatDataFromFormForPreview() {
  const v = (id) => document.getElementById(id)?.value ?? '';
  const imgUrl = (imgId) => {
    const img = document.getElementById(imgId);
    if (!img || !img.src || img.style.display === 'none') return '';
    try {
      return img.src.split('?')[0];
    } catch (e) {
      return img.src || '';
    }
  };
  return {
    retreatHeroTitle: v('retreat-hero-title'),
    retreatHeroSubtitle: v('retreat-hero-subtitle'),
    retreatHeroImageUrl: imgUrl('preview-retreat-hero-img'),
    retreatLocationsTitle: v('retreat-locations-title'),
    retreatForestTitle: v('retreat-forest-title'),
    retreatForestDescription: v('retreat-forest-description'),
    retreatForestImageUrl: imgUrl('preview-retreat-forest-img'),
    retreatIndoorTitle: v('retreat-indoor-title'),
    retreatIndoorDescription: v('retreat-indoor-description'),
    retreatIndoorImageUrl: imgUrl('preview-retreat-indoor-img'),
    retreatTheatreTitle: v('retreat-theatre-title'),
    retreatTheatreDescription: v('retreat-theatre-description'),
    retreatTheatreImageUrl: imgUrl('preview-retreat-theatre-img'),
    retreatCollaborationImageUrl: imgUrl('preview-retreat-collaboration-img'),
    retreatContactTitle: v('retreat-contact-title'),
    retreatContactText: v('retreat-contact-text'),
    retreatCollaborationTitle: v('retreat-collaboration-title'),
    retreatCollaborationIntro: v('retreat-collaboration-intro')
  };
}

// Update retreat preview
function updateRetreatPreview(data) {
  // Temporarily disable auto-save flag updates during preview update
  // This prevents the flag from being reset when we sync form fields
  const wasUpdatingPreview = window.retreatUpdatingPreview;
  window.retreatUpdatingPreview = true;
  
  // Hero
  const heroTitleEl = document.getElementById('preview-retreat-hero-title');
  const heroSubtitleEl = document.getElementById('preview-retreat-hero-subtitle');
  const heroImgEl = document.getElementById('preview-retreat-hero-img');
  if (heroTitleEl) {
    heroTitleEl.textContent = data.retreatHeroTitle || 'Activities and Practices at Back to Base';
    // Sync to form
    const formField = document.getElementById('retreat-hero-title');
    if (formField) formField.value = heroTitleEl.textContent;
  }
  if (heroSubtitleEl) {
    heroSubtitleEl.textContent = data.retreatHeroSubtitle || 'Where nature and quiet become part of your practice';
    // Sync to form
    const formField = document.getElementById('retreat-hero-subtitle');
    if (formField) formField.value = heroSubtitleEl.textContent;
  }
  
  // Restore flag update state
  window.retreatUpdatingPreview = wasUpdatingPreview;
  if (heroImgEl && data.retreatHeroImageUrl) {
    heroImgEl.src = data.retreatHeroImageUrl;
    heroImgEl.style.display = 'block';
    heroImgEl.nextElementSibling.style.display = 'none';
  }

  // Invitation to Collaborate (preview order matches site: after Hero, before Outdoor / locations)
  const collaborationTitleEl = document.getElementById('preview-retreat-collaboration-title');
  const collaborationIntroEl = document.getElementById('preview-retreat-collaboration-intro');
  if (collaborationTitleEl) {
    collaborationTitleEl.textContent = data.retreatCollaborationTitle || 'Invitation to Collaborate';
    const formField = document.getElementById('retreat-collaboration-title');
    if (formField) formField.value = collaborationTitleEl.textContent;
  }
  if (collaborationIntroEl) {
    const fullText =
      data.retreatCollaborationIntro ||
      retreatDefaultValues['retreat-collaboration-intro'] ||
      '';
    collaborationIntroEl.textContent = fullText;
    const formField = document.getElementById('retreat-collaboration-intro');
    if (formField) formField.value = data.retreatCollaborationIntro || '';
  }
  const collaborationImgEl = document.getElementById('preview-retreat-collaboration-img');
  if (collaborationImgEl && data.retreatCollaborationImageUrl) {
    collaborationImgEl.src = data.retreatCollaborationImageUrl;
    collaborationImgEl.style.display = 'block';
    const ph = collaborationImgEl.nextElementSibling;
    if (ph && ph.tagName === 'SPAN') ph.style.display = 'none';
  }
  
  // Locations title
  const locationsTitleEl = document.getElementById('preview-retreat-locations-title');
  if (locationsTitleEl) locationsTitleEl.textContent = data.retreatLocationsTitle || 'Our locations for your workshops';
  
  // Forest Platforms
  const forestTitleEl = document.getElementById('preview-retreat-forest-title');
  const forestDescEl = document.getElementById('preview-retreat-forest-desc');
  const forestImgEl = document.getElementById('preview-retreat-forest-img');
  if (forestTitleEl) {
    forestTitleEl.textContent = data.retreatForestTitle || 'Outdoor space with multi functional platforms';
    const formField = document.getElementById('retreat-forest-title');
    if (formField) formField.value = forestTitleEl.textContent;
  }
  if (forestDescEl) {
    const fullText = data.retreatForestDescription || 'Just a few steps from the house...';
    forestDescEl.textContent = fullText;
    const formField = document.getElementById('retreat-forest-description');
    if (formField) formField.value = data.retreatForestDescription || '';
  }
  if (forestImgEl && data.retreatForestImageUrl) {
    forestImgEl.src = data.retreatForestImageUrl;
    forestImgEl.style.display = 'block';
    forestImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Indoor Space
  const indoorTitleEl = document.getElementById('preview-retreat-indoor-title');
  const indoorDescEl = document.getElementById('preview-retreat-indoor-desc');
  const indoorImgEl = document.getElementById('preview-retreat-indoor-img');
  if (indoorTitleEl) {
    indoorTitleEl.textContent = data.retreatIndoorTitle || 'Multifunctional indoor space';
    const formField = document.getElementById('retreat-indoor-title');
    if (formField) formField.value = indoorTitleEl.textContent;
  }
  if (indoorDescEl) {
    const fullText = data.retreatIndoorDescription || 'Inside the house, there is a spacious room...';
    indoorDescEl.textContent = fullText;
    const formField = document.getElementById('retreat-indoor-description');
    if (formField) formField.value = data.retreatIndoorDescription || '';
  }
  if (indoorImgEl && data.retreatIndoorImageUrl) {
    indoorImgEl.src = data.retreatIndoorImageUrl;
    indoorImgEl.style.display = 'block';
    indoorImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Home Theatre
  const theatreTitleEl = document.getElementById('preview-retreat-theatre-title');
  const theatreDescEl = document.getElementById('preview-retreat-theatre-desc');
  const theatreImgEl = document.getElementById('preview-retreat-theatre-img');
  if (theatreTitleEl) {
    theatreTitleEl.textContent = data.retreatTheatreTitle || 'Cozy mini home theatre';
    const formField = document.getElementById('retreat-theatre-title');
    if (formField) formField.value = theatreTitleEl.textContent;
  }
  if (theatreDescEl) {
    const fullText = data.retreatTheatreDescription || 'For presentations, educational films...';
    // Show full text in preview (CSS will handle visual truncation)
    theatreDescEl.textContent = fullText;
    const formField = document.getElementById('retreat-theatre-description');
    if (formField) formField.value = data.retreatTheatreDescription || '';
  }
  if (theatreImgEl && data.retreatTheatreImageUrl) {
    theatreImgEl.src = data.retreatTheatreImageUrl;
    theatreImgEl.style.display = 'block';
    theatreImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Contact Form
  const contactTitleEl = document.getElementById('preview-retreat-contact-title');
  const contactTextEl = document.getElementById('preview-retreat-contact-text');
  if (contactTitleEl) {
    const fullText = data.retreatContactTitle || 'Are you looking for a place to retreat...';
    // Show full text in preview (CSS will handle visual truncation)
    contactTitleEl.textContent = fullText;
    const formField = document.getElementById('retreat-contact-title');
    if (formField) formField.value = data.retreatContactTitle || '';
  }
  if (contactTextEl) {
    const fullText = data.retreatContactText || 'Just send us a message...';
    // Show full text in preview (CSS will handle visual truncation)
    contactTextEl.textContent = fullText;
    const formField = document.getElementById('retreat-contact-text');
    if (formField) formField.value = data.retreatContactText || '';
  }
  
  // Restore flag update state after all form fields are updated
  window.retreatUpdatingPreview = wasUpdatingPreview;
}

// Update a specific field in the retreat preview
// Make it globally accessible for HTML oninput handlers
window.updateRetreatPreviewField = function(fieldKey, value) {
  const fieldMap = {
    'hero-title': { previewId: 'preview-retreat-hero-title', formId: 'retreat-hero-title' },
    'hero-subtitle': { previewId: 'preview-retreat-hero-subtitle', formId: 'retreat-hero-subtitle' },
    'locations-title': { previewId: 'preview-retreat-locations-title', formId: 'retreat-locations-title' },
    'forest-title': { previewId: 'preview-retreat-forest-title', formId: 'retreat-forest-title' },
    'forest-desc': { previewId: 'preview-retreat-forest-desc', formId: 'retreat-forest-description' },
    'indoor-title': { previewId: 'preview-retreat-indoor-title', formId: 'retreat-indoor-title' },
    'indoor-desc': { previewId: 'preview-retreat-indoor-desc', formId: 'retreat-indoor-description' },
    'theatre-title': { previewId: 'preview-retreat-theatre-title', formId: 'retreat-theatre-title' },
    'theatre-desc': { previewId: 'preview-retreat-theatre-desc', formId: 'retreat-theatre-description' },
    'contact-title': { previewId: 'preview-retreat-contact-title', formId: 'retreat-contact-title' },
    'contact-text': { previewId: 'preview-retreat-contact-text', formId: 'retreat-contact-text' },
    'collaboration-title': { previewId: 'preview-retreat-collaboration-title', formId: 'retreat-collaboration-title' },
    'collaboration-intro': { previewId: 'preview-retreat-collaboration-intro', formId: 'retreat-collaboration-intro' }
  };
  
  const mapping = fieldMap[fieldKey];
  if (!mapping) {
    console.warn(`Unknown field key: ${fieldKey}`);
    return;
  }
  
  const previewEl = document.getElementById(mapping.previewId);
  if (previewEl) {
    // Handle list items
    if (previewEl.tagName === 'UL' || previewEl.tagName === 'OL') {
      fillUlFromNewlineText(previewEl, value);
    } else {
      // Show full text in preview (CSS will handle visual truncation)
      previewEl.textContent = value;
    }
  }
  
  // Trigger auto-save if retreat section is active
  // Use window.scheduleRetreatAutoSave to ensure it's accessible
  if (typeof window.scheduleRetreatAutoSave === 'function') {
    if (typeof retreatHasUnsavedChanges !== 'undefined') {
      retreatHasUnsavedChanges = true;
    }
    console.log('Calling scheduleRetreatAutoSave from updateRetreatPreviewField');
    window.scheduleRetreatAutoSave();
  } else if (typeof scheduleRetreatAutoSave === 'function') {
    // Fallback to local function if window version not available
    if (typeof retreatHasUnsavedChanges !== 'undefined') {
      retreatHasUnsavedChanges = true;
    }
    scheduleRetreatAutoSave();
  } else {
    console.warn('scheduleRetreatAutoSave is not defined in updateRetreatPreviewField');
  }
};

// Load retreat images data
async function loadRetreatImagesData() {
  console.log('Loading retreat images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.retreatHeroImageUrl || '';
        const collaborationImageUrl = result.data.retreatCollaborationImageUrl || '';
        const forestImageUrl = result.data.retreatForestImageUrl || '';
        const indoorImageUrl = result.data.retreatIndoorImageUrl || '';
        const theatreImageUrl = result.data.retreatTheatreImageUrl || '';
        
        // Update previews
        updateImagePreview('retreat-hero', heroImageUrl);
        updateImagePreview('retreat-collaboration', collaborationImageUrl);
        updateImagePreview('retreat-forest', forestImageUrl);
        updateImagePreview('retreat-indoor', indoorImageUrl);
        updateImagePreview('retreat-theatre', theatreImageUrl);
        
        // Save to localStorage
        const stored = localStorage.getItem('btb_retreat_images') || '{}';
        const storedJson = JSON.parse(stored);
        const retreatImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          collaboration: collaborationImageUrl || storedJson.collaboration || '',
          forest: forestImageUrl || storedJson.forest || '',
          indoor: indoorImageUrl || storedJson.indoor || '',
          theatre: theatreImageUrl || storedJson.theatre || ''
        };
        localStorage.setItem('btb_retreat_images', JSON.stringify(retreatImagesData));
        console.log('Retreat images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load retreat images data:', error);
  }
}

// Helper function to update image preview
function updateImagePreview(prefix, imageUrl) {
  if (imageUrl) {
    const preview = document.getElementById(prefix + '-preview');
    const pathDisplay = document.getElementById(prefix + '-path');
    if (preview && pathDisplay) {
      const img = document.createElement('img');
      img.src = imageUrl + '?v=' + Date.now();
      preview.innerHTML = '';
      preview.appendChild(img);
      preview.style.display = 'block';
      pathDisplay.textContent = imageUrl;
      pathDisplay.style.display = 'block';
    }
  }
}

// All text fields that support line breaks - preserve whitespace for all contenteditable fields
// Since we added white-space: pre-wrap to all contenteditable fields in admin.html,
// we preserve whitespace for all fields by default
const PREVIEW_PRESERVE_WHITESPACE_FIELDS = new Set([
  // Homepage
  'homepage-main-description',
  'homepage-main-subtitle',
  // Floor Plan
  'floorplan-title',
  'floorplan-subtitle',
  'basement-subtitle',
  'basement-description',
  'ground-subtitle',
  'ground-description',
  'loft-subtitle',
  'loft-description',
  // Rooms Cards
  'rooms-title',
  'rooms-subtitle',
  'room-basement-card-title',
  'room-basement-card-description',
  'room-ground-queen-card-title',
  'room-ground-queen-card-description',
  'room-ground-twin-card-title',
  'room-ground-twin-card-description',
  'room-second-card-title',
  'room-second-card-description',
  // Wellness
  'wellness-description',
  'wellness-massage-description',
  // Massage
  'massage-intro',
  'massage-relaxing-description',
  'massage-deep-tissue-description',
  'massage-reiki-description',
  'massage-sauna-description',
  'massage-booking-intro',
  'mini-hotel-description',
  'explore-accommodation-description',
  // Retreat and Workshops
  'retreat-forest-description',
  'retreat-indoor-description',
  'retreat-theatre-description',
  'retreat-contact-text',
  'retreat-collaboration-intro',
  // Special
  'special-hero-title',
  'special-hero-subtitle',
  'special-offer-main-text',
  'special-offer-description',
  // About
  'about-hero-title',
  'about-hero-subtitle',
  'about-idea-intro',
  'about-idea-signature',
  'about-location-paragraph-1',
  'about-location-paragraph-2',
  'about-location-paragraph-3',
  'about-contact-form-title',
  'about-contact-form-description',
  // Contact
  'contact-phone',
  'contact-email',
  'contact-address',
  // Room detail pages
  'room-basement-subtitle',
  'room-basement-description',
  'room-basement-note',
  'room-ground-queen-subtitle',
  'room-ground-queen-description',
  'room-ground-queen-note',
  'room-ground-twin-subtitle',
  'room-ground-twin-description',
  'room-ground-twin-note',
  'room-second-subtitle',
  'room-second-description',
  'room-second-note',
  // Wellness / massage titles (optional multiline)
  'wellness-title',
  'wellness-massage-title',
  'massage-hero-title',
  'massage-relaxing-title',
  'massage-deep-tissue-title',
  'massage-reiki-title',
  'massage-sauna-title',
  'massage-booking-title',
  'mini-hotel-title',
  // Explore section intros (multiline)
  'explore-communities-intro',
  'explore-culture-intro',
  'explore-activities-intro',
  'about-parks-intro',
]);

/**
 * Fill a <ul> preview from newline-separated stored text (escapes HTML per <li>).
 */
function fillUlFromNewlineText(ulElement, rawText) {
  if (!ulElement) return;
  const items = String(rawText || '')
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean);
  ulElement.innerHTML = '';
  items.forEach((text) => {
    const li = document.createElement('li');
    li.textContent = text;
    ulElement.appendChild(li);
  });
}

/**
 * Replace nested ul/ol in a clone with plain text so list structure is preserved on save.
 * Uses "- item" lines for ul (matches site safeOutputWithBreaks) and "1. item" for ol.
 */
function flattenContentEditableListsToPlainText(clone) {
  for (let guard = 0; guard < 64; guard++) {
    const leafLists = Array.from(clone.querySelectorAll('ul, ol')).filter(
      (list) => !list.querySelector('ul, ol')
    );
    if (leafLists.length === 0) break;
    leafLists.forEach((list) => {
      const isOl = list.tagName === 'OL';
      const items = Array.from(list.querySelectorAll(':scope > li'));
      const lines = items
        .map((li, idx) => {
          const t = (li.textContent || '').replace(/\r\n/g, '\n').trim();
          if (!t) return '';
          return isOl ? `${idx + 1}. ${t}` : `- ${t}`;
        })
        .filter(Boolean);
      list.replaceWith(document.createTextNode(lines.join('\n')));
    });
  }
}

/**
 * innerText on a detached DOM tree often omits newlines between block elements (e.g. divs from Enter
 * in contenteditable). Mount in a hidden pre-wrap container so layout matches the live preview.
 */
function getPlainTextFromContentEditablePreviewClone(clone) {
  clone.querySelectorAll('br').forEach((br) => {
    br.replaceWith(document.createTextNode('\n'));
  });
  const wrap = document.createElement('div');
  wrap.setAttribute('aria-hidden', 'true');
  // Do not use visibility:hidden: innerText/layout for "invisible" subtrees can collapse
  // block boundaries (newlines) differently across browsers. Opacity keeps layout usable.
  wrap.style.cssText =
    'position:fixed;left:-9999px;top:0;white-space:pre-wrap;width:min(800px,100vw);font:inherit;opacity:0;pointer-events:none;z-index:-1;';
  wrap.appendChild(clone);
  document.body.appendChild(wrap);
  let text = '';
  try {
    text = typeof wrap.innerText === 'string' ? wrap.innerText : '';
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    // While wrap is in the document, join explicit top-level blocks if innerText still has no newlines
    if (!text.includes('\n')) {
      const blocks = Array.from(clone.children).filter((el) =>
        ['DIV', 'P', 'BLOCKQUOTE', 'SECTION', 'ARTICLE'].includes(el.tagName)
      );
      if (blocks.length > 1) {
        text = blocks
          .map((el) => (el.innerText || el.textContent || '').replace(/\r\n/g, '\n').trim())
          .filter(Boolean)
          .join('\n');
      }
    }
  } finally {
    wrap.remove();
  }
  return text;
}

// Sync preview content to form field
function syncPreviewToForm(previewElement, fieldId) {
  const formField = document.getElementById(fieldId);
  if (formField && previewElement) {
    let content = '';
    
    // Handle list items - convert to newline-separated format
    if (previewElement.tagName === 'UL' || previewElement.tagName === 'OL') {
      const items = Array.from(previewElement.querySelectorAll(':scope > li'))
        .map((li) => (li.textContent || '').replace(/\r\n/g, '\n').trim())
        .filter(Boolean);
      content = items.join('\n');
    } else {
      // For contenteditable divs: flatten lists, then read plain text from a mounted clone (see getPlainTextFromContentEditablePreviewClone).
      const clone = previewElement.cloneNode(true);
      flattenContentEditableListsToPlainText(clone);
      content = getPlainTextFromContentEditablePreviewClone(clone);
      // Legacy DB / paste may store line breaks as literal "<br>" in strings — normalize for all sections
      content = content.replace(/<br\s*\/?>/gi, '\n');
      // Remove leading/trailing whitespace unless field needs to preserve it
      if (!PREVIEW_PRESERVE_WHITESPACE_FIELDS.has(fieldId)) {
        content = content.trim();
      }
    }
    
    if (fieldId === 'explore-accommodation-title') {
      const inh = (window.__exploreAccommodationInherited || {})[fieldId];
      const inhS = String(inh != null ? inh : '');
      const inhPlain =
        inhS.indexOf('<') === -1
          ? inhS.replace(/\r\n/g, '\n').trim()
          : (() => {
              const d = document.createElement('div');
              d.innerHTML = inhS.replace(/<br\s*\/?>/gi, '\n');
              return (d.textContent || '').replace(/\r\n/g, '\n').trim();
            })();
      const cNorm = String(content).replace(/\r\n/g, '\n').trim();
      if (cNorm === '' || (inhPlain !== '' && cNorm === inhPlain)) {
        content = '';
      }
    } else if (fieldId === 'explore-accommodation-description') {
      const inh = (window.__exploreAccommodationInherited || {})[fieldId];
      const inhPlain = String(inh != null ? inh : '')
        .replace(/\r\n/g, '\n')
        .trim();
      const cNorm = String(content).replace(/\r\n/g, '\n').trim();
      if (cNorm === '' || (inhPlain !== '' && cNorm === inhPlain)) {
        content = '';
      }
    } else if (fieldId === 'mini-hotel-description') {
      const inh = (window.__miniHotelDescriptionInherited || {})[fieldId];
      const inhPlain = String(inh != null ? inh : '')
        .replace(/\r\n/g, '\n')
        .trim();
      const cNorm = String(content).replace(/\r\n/g, '\n').trim();
      if (cNorm === '' || (inhPlain !== '' && cNorm === inhPlain)) {
        content = '';
      }
    }
    
    const oldValue = formField.value;
    
    // Only trigger auto-save if value actually changed
    if (oldValue !== content) {
      formField.value = content;

      // Explore: section headers and sticky-nav row share the same hidden field per H2
      if (
        fieldId === 'explore-communities-h2' ||
        fieldId === 'explore-culture-h2' ||
        fieldId === 'explore-parks-h2' ||
        fieldId === 'explore-activities-h2'
      ) {
        document.querySelectorAll(`[data-field="${fieldId}"]`).forEach((el) => {
          if (el !== previewElement) {
            el.textContent = content;
          }
        });
      }
      
      // Mark as having unsaved changes BEFORE triggering input event
      // This ensures the flag is set before any event handlers run
      if (fieldId.startsWith('retreat-')) {
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = true;
          console.log('Set retreatHasUnsavedChanges = true for field:', fieldId, 'oldValue:', String(oldValue || '').substring(0, 30), 'newValue:', String(content || '').substring(0, 30));
        }
      }
      
      // Mark contact fields as having unsaved changes
      if (fieldId.startsWith('contact-')) {
        if (typeof contactHasUnsavedChanges !== 'undefined') {
          contactHasUnsavedChanges = true;
          console.log('Set contactHasUnsavedChanges = true for field:', fieldId);
        }
      }
      
      // Trigger input event to update other previews if needed
      // This might trigger handlers that check retreatHasUnsavedChanges, so we set it first
      formField.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Schedule auto-save for retreat section
      if (fieldId.startsWith('retreat-')) {
        // Use window.scheduleRetreatAutoSave to ensure it's accessible
        if (typeof window.scheduleRetreatAutoSave === 'function') {
          console.log('Scheduling auto-save for field:', fieldId);
          window.scheduleRetreatAutoSave();
        } else if (typeof scheduleRetreatAutoSave === 'function') {
          // Fallback to local function
          scheduleRetreatAutoSave();
        } else {
          console.warn('scheduleRetreatAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for special section
      if (fieldId.startsWith('special-')) {
        if (typeof scheduleSpecialAutoSave === 'function') {
          if (typeof specialHasUnsavedChanges !== 'undefined') {
            specialHasUnsavedChanges = true;
          }
          scheduleSpecialAutoSave();
        } else {
          console.warn('scheduleSpecialAutoSave is not defined');
        }
      }
      
      // Explore page CMS (DB fields: explore_* + about_attractions / cards / parks) vs About us only
      if (fieldId.startsWith('explore-') || (fieldId.startsWith('about-') && typeof EXPLORE_CMS_FIELD_IDS !== 'undefined' && EXPLORE_CMS_FIELD_IDS.has(fieldId))) {
        if (typeof scheduleExploreAutoSave === 'function') {
          if (typeof exploreHasUnsavedChanges !== 'undefined') {
            exploreHasUnsavedChanges = true;
          }
          scheduleExploreAutoSave();
        } else {
          console.warn('scheduleExploreAutoSave is not defined');
        }
      } else if (fieldId.startsWith('about-')) {
        if (typeof scheduleAboutAutoSave === 'function') {
          if (typeof aboutHasUnsavedChanges !== 'undefined') {
            aboutHasUnsavedChanges = true;
          }
          scheduleAboutAutoSave();
        } else {
          console.warn('scheduleAboutAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for contact section
      if (fieldId.startsWith('contact-')) {
        // Use window.scheduleContactAutoSave to ensure it's accessible
        if (typeof window.scheduleContactAutoSave === 'function') {
          console.log('Scheduling contact auto-save for field:', fieldId);
          window.scheduleContactAutoSave();
        } else if (typeof scheduleContactAutoSave === 'function') {
          // Fallback to local function
          scheduleContactAutoSave();
        } else {
          console.warn('scheduleContactAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for massage section
      if (fieldId.startsWith('massage-') || fieldId.startsWith('mini-hotel-')) {
        console.log('Triggering massage auto-save for field:', fieldId);
        if (typeof window.scheduleMassageAutoSave === 'function') {
          if (typeof massageHasUnsavedChanges !== 'undefined') {
            massageHasUnsavedChanges = true;
            console.log('Set massageHasUnsavedChanges = true for field:', fieldId);
          }
          window.scheduleMassageAutoSave();
        } else {
          console.warn('scheduleMassageAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for room-second section
      if (fieldId.startsWith('room-second-')) {
        if (typeof window.scheduleRoomSecondAutoSave === 'function') {
          if (typeof roomSecondHasUnsavedChanges !== 'undefined') {
            roomSecondHasUnsavedChanges = true;
          }
          window.scheduleRoomSecondAutoSave();
        } else {
          console.warn('scheduleRoomSecondAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for homepage section
      if (fieldId.startsWith('homepage-')) {
        if (typeof window.scheduleHomepageAutoSave === 'function') {
          if (typeof homepageHasUnsavedChanges !== 'undefined') {
            homepageHasUnsavedChanges = true;
          }
          window.scheduleHomepageAutoSave();
        } else if (typeof scheduleHomepageAutoSave === 'function') {
          homepageHasUnsavedChanges = true;
          scheduleHomepageAutoSave();
        } else {
          console.warn('scheduleHomepageAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for floorplan section
      if (fieldId.startsWith('floorplan-') || fieldId.startsWith('basement-') || fieldId.startsWith('ground-') || fieldId.startsWith('loft-')) {
        console.log('Floorplan field changed:', fieldId, 'Scheduling auto-save...');
        if (typeof window.scheduleFloorplanAutoSave === 'function') {
          if (typeof floorplanHasUnsavedChanges !== 'undefined') {
            floorplanHasUnsavedChanges = true;
          }
          console.log('Calling window.scheduleFloorplanAutoSave');
          window.scheduleFloorplanAutoSave();
        } else if (typeof scheduleFloorplanAutoSave === 'function') {
          floorplanHasUnsavedChanges = true;
          console.log('Calling scheduleFloorplanAutoSave');
          scheduleFloorplanAutoSave();
        } else {
          console.warn('scheduleFloorplanAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for homepage-rooms section
      if (fieldId.startsWith('rooms-') || fieldId.startsWith('room-basement-card-') || fieldId.startsWith('room-ground-queen-card-') || fieldId.startsWith('room-ground-twin-card-') || fieldId.startsWith('room-second-card-')) {
        console.log('Rooms field changed:', fieldId, 'Scheduling auto-save...');
        if (typeof window.scheduleHomepageRoomsAutoSave === 'function') {
          if (typeof homepageRoomsHasUnsavedChanges !== 'undefined') {
            homepageRoomsHasUnsavedChanges = true;
          }
          console.log('Calling window.scheduleHomepageRoomsAutoSave');
          window.scheduleHomepageRoomsAutoSave();
        } else if (typeof scheduleHomepageRoomsAutoSave === 'function') {
          homepageRoomsHasUnsavedChanges = true;
          console.log('Calling scheduleHomepageRoomsAutoSave');
          scheduleHomepageRoomsAutoSave();
        } else {
          console.warn('scheduleHomepageRoomsAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for room-ground-twin section
      if (fieldId.startsWith('room-ground-twin-')) {
        if (typeof window.scheduleRoomGroundTwinAutoSave === 'function') {
          if (typeof roomGroundTwinHasUnsavedChanges !== 'undefined') {
            roomGroundTwinHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundTwinAutoSave();
        } else {
          console.warn('scheduleRoomGroundTwinAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for room-ground-queen section
      if (fieldId.startsWith('room-ground-queen-')) {
        if (typeof window.scheduleRoomGroundQueenAutoSave === 'function') {
          if (typeof roomGroundQueenHasUnsavedChanges !== 'undefined') {
            roomGroundQueenHasUnsavedChanges = true;
          }
          window.scheduleRoomGroundQueenAutoSave();
        } else {
          console.warn('scheduleRoomGroundQueenAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for room-basement section
      if (fieldId.startsWith('room-basement-')) {
        if (typeof window.scheduleRoomBasementAutoSave === 'function') {
          if (typeof roomBasementHasUnsavedChanges !== 'undefined') {
            roomBasementHasUnsavedChanges = true;
          }
          window.scheduleRoomBasementAutoSave();
        } else {
          console.warn('scheduleRoomBasementAutoSave is not defined');
        }
      }
      
      // Schedule auto-save for wellness section
      if (fieldId.startsWith('wellness-')) {
        if (typeof window.scheduleWellnessAutoSave === 'function') {
          if (typeof wellnessHasUnsavedChanges !== 'undefined') {
            wellnessHasUnsavedChanges = true;
          }
          window.scheduleWellnessAutoSave();
        } else {
          console.warn('scheduleWellnessAutoSave is not defined');
        }
      }
      
      console.log(`Synced preview to form: ${fieldId} = "${content.substring(0, 50)}..."`);
    }
    // Do not fall back to previewElement.textContent when oldValue === content: textContent omits
    // newlines between block nodes (Enter in contenteditable), which would wipe line breaks for retreat.
  } else {
    console.error(`Failed to sync: formField=${!!formField}, previewElement=${!!previewElement}, fieldId=${fieldId}`);
  }
}

// Show image edit button on hover
function showImageEditButton(container) {
  const btn = container.querySelector('.image-edit-btn');
  if (btn) {
    btn.style.display = 'block';
  }
}

// Hide image edit button on mouse leave
function hideImageEditButton(container) {
  const btn = container.querySelector('.image-edit-btn');
  if (btn) {
    btn.style.display = 'none';
  }
}

// Trigger image upload
function triggerImageUpload(inputId) {
  const fileInput = document.getElementById(inputId);
  if (fileInput) {
    fileInput.click();
  }
}

// Initialize retreat image upload
function initRetreatImageUpload() {
  const uploadConfigs = [
    { buttonId: 'retreat-hero-upload-btn', inputId: 'retreat-hero-upload', previewId: 'retreat-hero-preview', pathId: 'retreat-hero-path', imageType: 'retreat-hero' },
    { buttonId: 'retreat-forest-upload-btn', inputId: 'retreat-forest-upload', previewId: 'retreat-forest-preview', pathId: 'retreat-forest-path', imageType: 'retreat-forest' },
    { buttonId: 'retreat-indoor-upload-btn', inputId: 'retreat-indoor-upload', previewId: 'retreat-indoor-preview', pathId: 'retreat-indoor-path', imageType: 'retreat-indoor' },
    { buttonId: 'retreat-theatre-upload-btn', inputId: 'retreat-theatre-upload', previewId: 'retreat-theatre-preview', pathId: 'retreat-theatre-path', imageType: 'retreat-theatre' },
    { buttonId: 'retreat-collaboration-upload-btn', inputId: 'retreat-collaboration-upload', previewId: 'retreat-collaboration-preview', pathId: 'retreat-collaboration-path', imageType: 'retreat-collaboration' }
  ];

  uploadConfigs.forEach(config => {
    const uploadBtn = document.getElementById(config.buttonId);
    const fileInput = document.getElementById(config.inputId);
    const preview = document.getElementById(config.previewId);
    const pathDisplay = document.getElementById(config.pathId);

    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          // Update preview image immediately
          const previewImgId = 'preview-' + config.imageType.replace('-', '-') + '-img';
          const previewImg = document.getElementById(previewImgId);
          if (previewImg) {
            const reader = new FileReader();
            reader.onload = (event) => {
              previewImg.src = event.target.result;
              previewImg.style.display = 'block';
              const placeholder = previewImg.nextElementSibling;
              if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
          }
          
          await uploadImage(file, config.imageType, preview, pathDisplay, {
            localStorageKey: 'btb_retreat_images',
            fieldNameMapper: (type) => type.replace('retreat-', ''),
            reloadFunction: loadRetreatImagesData,
            imageNameMapper: (type) => {
              const nameMap = {
                'retreat-hero': 'Hero',
                'retreat-forest': 'Outdoor space',
                'retreat-indoor': 'Multifunctional indoor space',
                'retreat-theatre': 'Home Theatre',
                'retreat-collaboration': 'Invitation to Collaborate'
              };
              return nameMap[type] || type;
            }
          });
        }
      });
    }
  });
}

// Initialize save handler for retreat images
function initRetreatSaveHandler() {
  const saveBtn = document.getElementById('save-retreat-images');
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      const heroPath = document.getElementById('retreat-hero-path')?.textContent || '';
      const collaborationPath = document.getElementById('retreat-collaboration-path')?.textContent || '';
      const forestPath = document.getElementById('retreat-forest-path')?.textContent || '';
      const indoorPath = document.getElementById('retreat-indoor-path')?.textContent || '';
      const theatrePath = document.getElementById('retreat-theatre-path')?.textContent || '';
      
      const stored = localStorage.getItem('btb_content') || '{}';
      let contentData = {};
      try {
        contentData = JSON.parse(stored);
      } catch (e) {
        console.error('Failed to parse btb_content:', e);
      }
      
      contentData.retreatHeroImageUrl = heroPath;
      contentData.retreatCollaborationImageUrl = collaborationPath;
      contentData.retreatForestImageUrl = forestPath;
      contentData.retreatIndoorImageUrl = indoorPath;
      contentData.retreatTheatreImageUrl = theatrePath;
      
      localStorage.setItem('btb_content', JSON.stringify(contentData));
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_content');
        formData.append('retreat_hero_image_url', heroPath);
        formData.append('retreat_collaboration_image_url', collaborationPath);
        formData.append('retreat_forest_image_url', forestPath);
        formData.append('retreat_indoor_image_url', indoorPath);
        formData.append('retreat_theatre_image_url', theatrePath);
        
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        const rtxt = await response.text();
        if (recordSaveContentResponse(response, rtxt, 'retreat')) {
          showStatus('Retreat images saved to database');
        } else {
          showStatus('Retreat images: localStorage only — server did not confirm save. See status line.', 'warning');
        }
      } catch (error) {
        console.error('Error saving retreat images:', error);
        showStatus('Retreat images: server save error', 'error');
        updateAdminSectionSaveStatus('retreat', 'error', (error && error.message) || 'Save failed');
      }
    });
  }
}

// Load special data
async function loadSpecialData() {
  console.log('Loading special page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section - update hidden fields and preview
        const heroTitleField = document.getElementById('special-hero-title');
        const heroSubtitleField = document.getElementById('special-hero-subtitle');
        const heroTitlePreview = document.getElementById('preview-special-hero-title');
        const heroSubtitlePreview = document.getElementById('preview-special-hero-subtitle');
        if (heroTitleField) heroTitleField.value = data.specialHeroTitle || '';
        if (heroSubtitleField) heroSubtitleField.value = data.specialHeroSubtitle || '';
        if (heroTitlePreview) heroTitlePreview.textContent = data.specialHeroTitle || 'Soak & Savor at Ainsworth Hot Springs';
        if (heroSubtitlePreview) heroSubtitlePreview.textContent = data.specialHeroSubtitle || 'Back to Base offers its guests a unique relaxation experience. See the details below.';
        
        // Mineral-Rich Pools & Limestone Cave card
        const poolsTitleField = document.getElementById('special-pools-title');
        const poolsDesc1Field = document.getElementById('special-pools-description-1');
        const poolsDesc2Field = document.getElementById('special-pools-description-2');
        const poolsTitlePreview = document.getElementById('preview-special-pools-title');
        const poolsDescPreview = document.getElementById('preview-special-pools-desc');
        if (poolsTitleField) poolsTitleField.value = data.specialPoolsTitle || '';
        if (poolsDesc1Field) poolsDesc1Field.value = data.specialPoolsDescription1 || '';
        if (poolsDesc2Field) poolsDesc2Field.value = data.specialPoolsDescription2 || '';
        if (poolsTitlePreview) poolsTitlePreview.textContent = data.specialPoolsTitle || 'Mineral-Rich Pools & Limestone Cave';
        if (poolsDescPreview) {
          const d1 = (data.specialPoolsDescription1 || '').trim();
          const d2 = (data.specialPoolsDescription2 || '').trim();
          const combined = [d1, d2].filter(Boolean).join('\n\n');
          poolsDescPreview.textContent =
            combined ||
            'The Ainsworth Hot Springs are located just a thirty-minute scenic drive from the Back to Base lodge. Relax in the mineral-rich waters of the pools and explore the unique limestone cave, where warm geothermal water flows along the grotto walls, creating a truly one-of-a-kind atmosphere for deep relaxation.';
        }
        
        // Dining & Spa Experience card
        const diningTitleField = document.getElementById('special-dining-title');
        const diningDesc1Field = document.getElementById('special-dining-description-1');
        const diningDesc2Field = document.getElementById('special-dining-description-2');
        const diningTitlePreview = document.getElementById('preview-special-dining-title');
        const diningDescPreview = document.getElementById('preview-special-dining-desc');
        if (diningTitleField) diningTitleField.value = data.specialDiningTitle || '';
        if (diningDesc1Field) diningDesc1Field.value = data.specialDiningDescription1 || '';
        if (diningDesc2Field) diningDesc2Field.value = data.specialDiningDescription2 || '';
        if (diningTitlePreview) diningTitlePreview.textContent = data.specialDiningTitle || 'Dining & Spa Experience';
        if (diningDescPreview) {
          const d1 = (data.specialDiningDescription1 || '').trim();
          const d2 = (data.specialDiningDescription2 || '').trim();
          const combined = [d1, d2].filter(Boolean).join('\n\n');
          diningDescPreview.textContent =
            combined ||
            'After your soak, enjoy a meal at the Ktunaxa Grill restaurant located on site. The menu features fresh regional ingredients and creative preparation, making every dish a real delight. Consider visiting the Spirit Water Spa, where experienced therapists offer a full range of treatments.';
        }

        const extraTitleField = document.getElementById('special-extra-title');
        const extraDesc1Field = document.getElementById('special-extra-description-1');
        const extraDesc2Field = document.getElementById('special-extra-description-2');
        const extraTitlePreview = document.getElementById('preview-special-extra-title');
        const extraDescPreview = document.getElementById('preview-special-extra-desc');
        if (extraTitleField) extraTitleField.value = data.specialExtraTitle || '';
        if (extraDesc1Field) extraDesc1Field.value = data.specialExtraDescription1 || '';
        if (extraDesc2Field) extraDesc2Field.value = data.specialExtraDescription2 || '';
        if (extraTitlePreview) extraTitlePreview.textContent = data.specialExtraTitle || 'Discover Nelson & the Kootenays';
        if (extraDescPreview) {
          const ex1 = (data.specialExtraDescription1 || '').trim();
          const ex2 = (data.specialExtraDescription2 || '').trim();
          extraDescPreview.textContent = [ex1, ex2].filter(Boolean).join('\n\n') ||
            'Beyond the hot springs, the lively town of Nelson offers galleries, cafés, and lakefront strolls — an ideal complement to your retreat.\n\nAsk us for tips on hikes, paddling on Kootenay Lake, or seasonal events during your stay.';
        }
        
        // Exclusive Offer card
        const offerTitleField = document.getElementById('special-offer-title');
        const offerMainField = document.getElementById('special-offer-main-text');
        const offerDescField = document.getElementById('special-offer-description');
        const offerTitlePreview = document.getElementById('preview-special-offer-title');
        const offerMainPreview = document.getElementById('preview-special-offer-main');
        const offerDescPreview = document.getElementById('preview-special-offer-desc');
        if (offerTitleField) offerTitleField.value = data.specialOfferTitle || '';
        if (offerMainField) offerMainField.value = data.specialOfferMainText || '';
        if (offerDescField) offerDescField.value = data.specialOfferDescription || '';
        if (offerTitlePreview) offerTitlePreview.textContent = data.specialOfferTitle || 'Free Hot Springs Access';
        if (offerMainPreview) offerMainPreview.textContent = data.specialOfferMainText || 'Exclusive Offer: Book a minimum 5-night stay at Kelder and receive one free visit per person to Ainsworth Hot Springs pools, courtesy of us!';
        if (offerDescPreview) offerDescPreview.textContent = data.specialOfferDescription || 'This exclusive offer includes access to the mineral-rich pools and the natural limestone cave. A perfect way to enhance your stay at Back to Base with a truly restorative experience.';

        const extraImgPrev = document.getElementById('preview-special-extra-img');
        if (extraImgPrev && data.specialExtraImageUrl) {
          extraImgPrev.src = data.specialExtraImageUrl + '?v=' + Date.now();
          extraImgPrev.style.display = 'block';
          const placeholder = extraImgPrev.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        console.log('Special page content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load special page data:', error);
  }
}

// Update special preview
function updateSpecialPreview(data) {
  // Hero
  const heroTitleEl = document.getElementById('preview-special-hero-title');
  const heroSubtitleEl = document.getElementById('preview-special-hero-subtitle');
  const heroImgEl = document.getElementById('preview-special-hero-img');
  if (heroTitleEl) heroTitleEl.textContent = data.specialHeroTitle || 'Soak & Savor at Ainsworth Hot Springs';
  if (heroSubtitleEl) heroSubtitleEl.textContent = (data.specialHeroSubtitle || 'Back to Base offers its guests...').substring(0, 60) + '...';
  if (heroImgEl && data.specialHeroImageUrl) {
    heroImgEl.src = data.specialHeroImageUrl;
    heroImgEl.style.display = 'block';
    heroImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Pools
  const poolsTitleEl = document.getElementById('preview-special-pools-title');
  const poolsDescEl = document.getElementById('preview-special-pools-desc');
  const poolsImgEl = document.getElementById('preview-special-pools-img');
  if (poolsTitleEl) poolsTitleEl.textContent = data.specialPoolsTitle || 'Mineral-Rich Pools & Limestone Cave';
  if (poolsDescEl) {
    const desc = (data.specialPoolsDescription1 || '') + ' ' + (data.specialPoolsDescription2 || '');
    poolsDescEl.textContent = desc ? (desc.substring(0, 100) + (desc.length > 100 ? '...' : '')) : 'The Ainsworth Hot Springs are located...';
  }
  if (poolsImgEl && data.specialPoolsImageUrl) {
    poolsImgEl.src = data.specialPoolsImageUrl;
    poolsImgEl.style.display = 'block';
    poolsImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Dining
  const diningTitleEl = document.getElementById('preview-special-dining-title');
  const diningDescEl = document.getElementById('preview-special-dining-desc');
  const diningImgEl = document.getElementById('preview-special-dining-img');
  if (diningTitleEl) diningTitleEl.textContent = data.specialDiningTitle || 'Dining & Spa Experience';
  if (diningDescEl) {
    const desc = (data.specialDiningDescription1 || '') + ' ' + (data.specialDiningDescription2 || '');
    diningDescEl.textContent = desc ? (desc.substring(0, 100) + (desc.length > 100 ? '...' : '')) : 'After your soak, enjoy a meal...';
  }
  if (diningImgEl && data.specialDiningImageUrl) {
    diningImgEl.src = data.specialDiningImageUrl;
    diningImgEl.style.display = 'block';
    diningImgEl.nextElementSibling.style.display = 'none';
  }

  // Extra block (after dining)
  const extraTitleEl = document.getElementById('preview-special-extra-title');
  const extraDescEl = document.getElementById('preview-special-extra-desc');
  const extraImgEl = document.getElementById('preview-special-extra-img');
  if (extraTitleEl) extraTitleEl.textContent = data.specialExtraTitle || 'Discover Nelson & the Kootenays';
  if (extraDescEl) {
    const ex1 = (data.specialExtraDescription1 || '').trim();
    const ex2 = (data.specialExtraDescription2 || '').trim();
    const desc = [ex1, ex2].filter(Boolean).join(' ');
    extraDescEl.textContent = desc ? (desc.substring(0, 100) + (desc.length > 100 ? '...' : '')) : 'Beyond the hot springs...';
  }
  if (extraImgEl && data.specialExtraImageUrl) {
    extraImgEl.src = data.specialExtraImageUrl;
    extraImgEl.style.display = 'block';
    if (extraImgEl.nextElementSibling) extraImgEl.nextElementSibling.style.display = 'none';
  }
  
  // Offer
  const offerTitleEl = document.getElementById('preview-special-offer-title');
  const offerMainEl = document.getElementById('preview-special-offer-main');
  const offerDescEl = document.getElementById('preview-special-offer-desc');
  if (offerTitleEl) offerTitleEl.textContent = data.specialOfferTitle || 'Free Hot Springs Access';
  if (offerMainEl) offerMainEl.textContent = (data.specialOfferMainText || 'Exclusive Offer: Book a minimum 5-night stay...').substring(0, 60) + '...';
  if (offerDescEl) offerDescEl.textContent = (data.specialOfferDescription || 'Book a minimum 5-night stay...').substring(0, 80) + '...';
}

// Update special preview field in real-time
function updateSpecialPreviewField(field, value) {
  const previewMap = {
    'hero-title': 'preview-special-hero-title',
    'hero-subtitle': 'preview-special-hero-subtitle',
    'pools-title': 'preview-special-pools-title',
    'pools-desc': 'preview-special-pools-desc',
    'dining-title': 'preview-special-dining-title',
    'dining-desc': 'preview-special-dining-desc',
    'extra-title': 'preview-special-extra-title',
    'extra-desc': 'preview-special-extra-desc',
    'offer-title': 'preview-special-offer-title',
    'offer-main': 'preview-special-offer-main',
    'offer-desc': 'preview-special-offer-desc'
  };
  
  const previewId = previewMap[field];
  if (previewId) {
    const el = document.getElementById(previewId);
    if (el) {
      if (field === 'hero-subtitle' || field === 'pools-desc' || field === 'dining-desc' || field === 'extra-desc' || field === 'offer-main' || field === 'offer-desc') {
        el.textContent = value ? (value.substring(0, 100) + (value.length > 100 ? '...' : '')) : '';
      } else {
        el.textContent = value || '';
      }
    }
  }
  
  // Handle combined descriptions
  if (field === 'pools-desc-1' || field === 'pools-desc-2') {
    const desc1 = field === 'pools-desc-1' ? value : document.getElementById('special-pools-description-1')?.value || '';
    const desc2 = field === 'pools-desc-2' ? value : document.getElementById('special-pools-description-2')?.value || '';
    const combined = (desc1 + ' ' + desc2).trim();
    const descEl = document.getElementById('preview-special-pools-desc');
    if (descEl) descEl.textContent = combined ? (combined.substring(0, 100) + (combined.length > 100 ? '...' : '')) : '';
  }
  if (field === 'dining-desc-1' || field === 'dining-desc-2') {
    const desc1 = field === 'dining-desc-1' ? value : document.getElementById('special-dining-description-1')?.value || '';
    const desc2 = field === 'dining-desc-2' ? value : document.getElementById('special-dining-description-2')?.value || '';
    const combined = (desc1 + ' ' + desc2).trim();
    const descEl = document.getElementById('preview-special-dining-desc');
    if (descEl) descEl.textContent = combined ? (combined.substring(0, 100) + (combined.length > 100 ? '...' : '')) : '';
  }
  if (field === 'extra-desc-1' || field === 'extra-desc-2') {
    const desc1 = field === 'extra-desc-1' ? value : document.getElementById('special-extra-description-1')?.value || '';
    const desc2 = field === 'extra-desc-2' ? value : document.getElementById('special-extra-description-2')?.value || '';
    const combined = (desc1 + ' ' + desc2).trim();
    const descEl = document.getElementById('preview-special-extra-desc');
    if (descEl) descEl.textContent = combined ? (combined.substring(0, 100) + (combined.length > 100 ? '...' : '')) : '';
  }
}

// Load special images data
async function loadSpecialImagesData() {
  console.log('Loading special images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.specialHeroImageUrl || '';
        const poolsImageUrl = result.data.specialPoolsImageUrl || '';
        const diningImageUrl = result.data.specialDiningImageUrl || '';
        const extraImageUrl = result.data.specialExtraImageUrl || '';
        
        // Update preview images directly
        const heroImg = document.getElementById('preview-special-hero-img');
        const poolsImg = document.getElementById('preview-special-pools-img');
        const diningImg = document.getElementById('preview-special-dining-img');
        const extraImg = document.getElementById('preview-special-extra-img');
        
        if (heroImg && heroImageUrl) {
          heroImg.src = heroImageUrl + '?v=' + Date.now();
          heroImg.style.display = 'block';
          const placeholder = heroImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        if (poolsImg && poolsImageUrl) {
          poolsImg.src = poolsImageUrl + '?v=' + Date.now();
          poolsImg.style.display = 'block';
          const placeholder = poolsImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        if (diningImg && diningImageUrl) {
          diningImg.src = diningImageUrl + '?v=' + Date.now();
          diningImg.style.display = 'block';
          const placeholder = diningImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }

        if (extraImg && extraImageUrl) {
          extraImg.src = extraImageUrl + '?v=' + Date.now();
          extraImg.style.display = 'block';
          const placeholder = extraImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        const stored = localStorage.getItem('btb_special_images') || '{}';
        const storedJson = JSON.parse(stored);
        const specialImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          pools: poolsImageUrl || storedJson.pools || '',
          dining: diningImageUrl || storedJson.dining || '',
          extra: extraImageUrl || storedJson.extra || ''
        };
        localStorage.setItem('btb_special_images', JSON.stringify(specialImagesData));
        console.log('Special images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load special images data:', error);
  }
}

// Initialize special image upload
function initSpecialImageUpload() {
  const uploadConfigs = [
    { inputId: 'special-hero-upload', previewImgId: 'preview-special-hero-img', imageType: 'special-hero' },
    { inputId: 'special-pools-upload', previewImgId: 'preview-special-pools-img', imageType: 'special-pools' },
    { inputId: 'special-dining-upload', previewImgId: 'preview-special-dining-img', imageType: 'special-dining' },
    { inputId: 'special-extra-upload', previewImgId: 'preview-special-extra-img', imageType: 'special-extra' }
  ];

  uploadConfigs.forEach(config => {
    const fileInput = document.getElementById(config.inputId);
    const previewImg = document.getElementById(config.previewImgId);

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          // Update preview image immediately
          if (previewImg) {
            const reader = new FileReader();
            reader.onload = (event) => {
              previewImg.src = event.target.result;
              previewImg.style.display = 'block';
              const placeholder = previewImg.nextElementSibling;
              if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
          }
          
          // Use universal uploadImage function
          await uploadImage(file, config.imageType, null, null, {
            localStorageKey: 'btb_special_images',
            fieldNameMapper: (type) => {
              // Convert 'special-hero' to 'hero', 'special-pools' to 'pools', etc.
              return type.replace('special-', '');
            },
            reloadFunction: loadSpecialImagesData,
            imageNameMapper: (type) => {
              const name = type.replace('special-', '');
              return name.charAt(0).toUpperCase() + name.slice(1);
            },
            onSuccess: (filepath) => {
              console.log('Special image upload success, filepath:', filepath, 'imageType:', config.imageType);
              
              // Save to localStorage for immediate site update
              const stored = localStorage.getItem('btb_special_images') || '{}';
              const storedJson = JSON.parse(stored);
              const imageKey = config.imageType.replace('special-', '');
              storedJson[imageKey] = filepath;
              localStorage.setItem('btb_special_images', JSON.stringify(storedJson));
              console.log('Saved to btb_special_images:', imageKey, '=', filepath);
              
              // Also save to btb_content for site display
              let contentData = {};
              const contentStored = localStorage.getItem('btb_content');
              if (contentStored) {
                try {
                  contentData = JSON.parse(contentStored);
                } catch (e) {
                  console.error('Failed to parse btb_content:', e);
                }
              }
              const fieldName = 'special' + (imageKey.charAt(0).toUpperCase() + imageKey.slice(1)) + 'ImageUrl';
              contentData[fieldName] = filepath;
              localStorage.setItem('btb_content', JSON.stringify(contentData));
              console.log('Saved to btb_content:', fieldName, '=', filepath);
              
              // Save to server via save_content (upload_image.php already saves, but this ensures consistency)
              const contentFormData = new FormData();
              contentFormData.append('action', 'save_content');
              const dbFieldName = 'special_' + imageKey.replace('-', '_') + '_image_url';
              contentFormData.append(dbFieldName, filepath);
              console.log('Saving to server with field name:', dbFieldName, '=', filepath);
              
              fetch('api.php', {
                method: 'POST',
                body: contentFormData
              }).then(response => response.json()).then(result => {
                console.log('Save content response:', result);
                // Trigger auto-save status update
                if (typeof scheduleSpecialAutoSave === 'function') {
                  scheduleSpecialAutoSave();
                }
              }).catch(error => {
                console.error('Error saving special image to content:', error);
              });
            }
          });
        }
      });
    }
  });
}

/**
 * Special page: one contenteditable shows two DB columns. Prefer splitting on a blank line (\n\n);
 * otherwise fall back to legacy sentence-based split for older content without explicit breaks.
 */
function splitSpecialCombinedIntoTwoFields(fullText) {
  const t = String(fullText || '')
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .replace(/<br\s*\/?>/gi, '\n');
  const trimmed = t.trim();
  if (!trimmed) return { desc1: '', desc2: '' };
  const idx = trimmed.indexOf('\n\n');
  if (idx !== -1) {
    return {
      desc1: trimmed.slice(0, idx).trim(),
      desc2: trimmed.slice(idx + 2).trim()
    };
  }
  const sentences = trimmed.match(/[^.!?]+[.!?]+/g) || [];
  if (sentences.length >= 2) {
    const midPoint = Math.floor(sentences.length / 2);
    return {
      desc1: sentences.slice(0, midPoint).join(' ').trim(),
      desc2: sentences.slice(midPoint).join(' ').trim()
    };
  }
  return { desc1: trimmed, desc2: '' };
}

// Sync special pools description (combines two fields)
window.syncSpecialPoolsDescription = function(previewElement) {
  const clone = previewElement.cloneNode(true);
  flattenContentEditableListsToPlainText(clone);
  const fullText = getPlainTextFromContentEditablePreviewClone(clone);
  const { desc1, desc2 } = splitSpecialCombinedIntoTwoFields(fullText);

  const desc1Field = document.getElementById('special-pools-description-1');
  const desc2Field = document.getElementById('special-pools-description-2');
  if (desc1Field) desc1Field.value = desc1;
  if (desc2Field) desc2Field.value = desc2;

  if (typeof scheduleSpecialAutoSave === 'function') {
    if (typeof specialHasUnsavedChanges !== 'undefined') {
      specialHasUnsavedChanges = true;
    }
    scheduleSpecialAutoSave();
  }
};

// Sync special extra block description (combines two fields)
window.syncSpecialExtraDescription = function(previewElement) {
  const clone = previewElement.cloneNode(true);
  flattenContentEditableListsToPlainText(clone);
  const fullText = getPlainTextFromContentEditablePreviewClone(clone);
  const { desc1, desc2 } = splitSpecialCombinedIntoTwoFields(fullText);

  const desc1Field = document.getElementById('special-extra-description-1');
  const desc2Field = document.getElementById('special-extra-description-2');
  if (desc1Field) desc1Field.value = desc1;
  if (desc2Field) desc2Field.value = desc2;

  if (typeof scheduleSpecialAutoSave === 'function') {
    if (typeof specialHasUnsavedChanges !== 'undefined') {
      specialHasUnsavedChanges = true;
    }
    scheduleSpecialAutoSave();
  }
};

window.syncSpecialDiningDescription = function(previewElement) {
  const clone = previewElement.cloneNode(true);
  flattenContentEditableListsToPlainText(clone);
  const fullText = getPlainTextFromContentEditablePreviewClone(clone);
  const { desc1, desc2 } = splitSpecialCombinedIntoTwoFields(fullText);

  const desc1Field = document.getElementById('special-dining-description-1');
  const desc2Field = document.getElementById('special-dining-description-2');
  if (desc1Field) desc1Field.value = desc1;
  if (desc2Field) desc2Field.value = desc2;

  if (typeof scheduleSpecialAutoSave === 'function') {
    if (typeof specialHasUnsavedChanges !== 'undefined') {
      specialHasUnsavedChanges = true;
    }
    scheduleSpecialAutoSave();
  }
};

// Special auto-save functionality
let specialAutoSaveTimer = null;
let specialHasUnsavedChanges = false;

function scheduleSpecialAutoSave() {
  if (specialAutoSaveTimer) {
    clearTimeout(specialAutoSaveTimer);
  }
  
  specialAutoSaveTimer = setTimeout(() => {
    if (specialHasUnsavedChanges) {
      saveSpecialContent();
      specialHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateSpecialSaveStatus('saving');
}

async function saveSpecialContent() {
  console.log('saveSpecialContent: Starting save...');
  updateSpecialSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    const heroTitle = document.getElementById('special-hero-title')?.value || '';
    const heroSubtitle = document.getElementById('special-hero-subtitle')?.value || '';
    const poolsTitle = document.getElementById('special-pools-title')?.value || '';
    const poolsDesc1 = document.getElementById('special-pools-description-1')?.value || '';
    const poolsDesc2 = document.getElementById('special-pools-description-2')?.value || '';
    const diningTitle = document.getElementById('special-dining-title')?.value || '';
    const diningDesc1 = document.getElementById('special-dining-description-1')?.value || '';
    const diningDesc2 = document.getElementById('special-dining-description-2')?.value || '';
    const extraTitle = document.getElementById('special-extra-title')?.value || '';
    const extraDesc1 = document.getElementById('special-extra-description-1')?.value || '';
    const extraDesc2 = document.getElementById('special-extra-description-2')?.value || '';
    const offerTitle = document.getElementById('special-offer-title')?.value || '';
    const offerMainText = document.getElementById('special-offer-main-text')?.value || '';
    const offerDescription = document.getElementById('special-offer-description')?.value || '';
    
    formData.append('special_hero_title', heroTitle);
    formData.append('special_hero_subtitle', heroSubtitle);
    formData.append('special_pools_title', poolsTitle);
    formData.append('special_pools_description_1', poolsDesc1);
    formData.append('special_pools_description_2', poolsDesc2);
    formData.append('special_dining_title', diningTitle);
    formData.append('special_dining_description_1', diningDesc1);
    formData.append('special_dining_description_2', diningDesc2);
    formData.append('special_extra_title', extraTitle);
    formData.append('special_extra_description_1', extraDesc1);
    formData.append('special_extra_description_2', extraDesc2);
    formData.append('special_offer_title', offerTitle);
    formData.append('special_offer_main_text', offerMainText);
    formData.append('special_offer_description', offerDescription);
    
    // Get image URLs from localStorage
    const imagesStored = localStorage.getItem('btb_special_images') || '{}';
    const imagesJson = JSON.parse(imagesStored);
    formData.append('special_hero_image_url', imagesJson.hero || '');
    formData.append('special_pools_image_url', imagesJson.pools || '');
    formData.append('special_dining_image_url', imagesJson.dining || '');
    formData.append('special_extra_image_url', imagesJson.extra || '');
    
    console.log('saveSpecialContent: Sending data:', {
      heroTitle: heroTitle.substring(0, 50),
      poolsTitle: poolsTitle.substring(0, 50),
      diningTitle: diningTitle.substring(0, 50),
      offerTitle: offerTitle.substring(0, 50)
    });
    
    await postApiFormDataAndUpdateStatus('special', formData);
  } catch (error) {
    console.error('Error saving special content:', error);
    updateSpecialSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateSpecialSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('special', status, detail);
}

function initSpecialAutoSave() {
  // syncPreviewToForm already handles triggering auto-save for special fields
  // This function is here for consistency with retreat pattern
  console.log('Special auto-save initialized');
}

// Explore page: DB fields that live under about_* and explore_* and are edited in the Explore section
const EXPLORE_CMS_FIELD_IDS = new Set([
  'explore-communities-intro', 'explore-culture-intro', 'explore-activities-intro',
  'about-procter-title', 'about-procter-distance', 'about-procter-description',
  'about-halcyon-title', 'about-halcyon-distance', 'about-halcyon-description',
  'about-whitewater-title', 'about-whitewater-distance', 'about-whitewater-description',
  'about-nelson-title', 'about-nelson-distance', 'about-nelson-description',
  'about-kaslo-title', 'about-kaslo-distance', 'about-kaslo-description',
  'about-crawford-title', 'about-crawford-distance', 'about-crawford-description',
  'about-museum-title', 'about-museum-distance', 'about-museum-description',
  'explore-communities-h2', 'explore-culture-h2', 'explore-parks-h2', 'explore-activities-h2',
  'about-parks-intro',
  'explore-hero-title', 'explore-hero-subtitle',
  'explore-accommodation-title', 'explore-accommodation-description',
]);

const attractionGalleries = ['procter', 'nelson', 'kaslo', 'crawford', 'museum', 'halcyon', 'whitewater'];

/** Sync Explore admin map coordinate inputs into hidden fields and schedule save. */
function syncExploreMapCoordFromPreview(inputEl, hiddenFieldId) {
  const hid = document.getElementById(hiddenFieldId);
  if (!hid || !inputEl) return;
  const v = String(inputEl.value || '').trim();
  if (hid.value !== v) {
    hid.value = v;
    if (typeof exploreHasUnsavedChanges !== 'undefined') {
      exploreHasUnsavedChanges = true;
    }
    if (typeof scheduleExploreAutoSave === 'function') {
      scheduleExploreAutoSave();
    }
  }
}
window.syncExploreMapCoordFromPreview = syncExploreMapCoordFromPreview;

/** Parse "lat, lng" or "lat lng" from admin map field into map_lat / map_lng. */
function parseParkCardMapCoordsInput(raw) {
  const s = String(raw || '').trim();
  if (!s) {
    return { map_lat: '', map_lng: '' };
  }
  const parts = s.split(/[,;\s]+/).map((p) => p.trim()).filter(Boolean);
  if (parts.length >= 2) {
    return { map_lat: parts[0], map_lng: parts[1] };
  }
  return { map_lat: '', map_lng: '' };
}

function formatParkCardMapCoords(lat, lng) {
  const a = String(lat != null ? lat : '').trim();
  const b = String(lng != null ? lng : '').trim();
  if (!a && !b) return '';
  return `${a}, ${b}`;
}

const EXPLORE_PARK_CARDS_MAX = 30;
const EXPLORE_SECTION_CARDS_MAX = 30;

const EXPLORE_SECTION_CARD_META = {
  communities: {
    rootId: 'explore-communities-cards-admin-root',
    hidRootId: 'explore-communities-cards-hidden-fields',
    jsonId: 'explore-communities-cards-json',
    uploadSlug: 'communities'
  },
  culture: {
    rootId: 'explore-culture-cards-admin-root',
    hidRootId: 'explore-culture-cards-hidden-fields',
    jsonId: 'explore-culture-cards-json',
    uploadSlug: 'culture'
  },
  activities: {
    rootId: 'explore-activities-cards-admin-root',
    hidRootId: 'explore-activities-cards-hidden-fields',
    jsonId: 'explore-activities-cards-json',
    uploadSlug: 'activities'
  }
};

function newExploreGalleryKey() {
  return 'g_' + Math.random().toString(36).slice(2, 11);
}

function parseGalleryJson(s) {
  try {
    const g = JSON.parse(s || '[]');
    return Array.isArray(g) ? g.filter((u) => typeof u === 'string' && u.trim()) : [];
  } catch (e) {
    return [];
  }
}

function defaultExploreCommunityCardsFromApi(data) {
  const raw = data && data.exploreCommunitiesCards != null ? String(data.exploreCommunitiesCards).trim() : '';
  if (raw !== '' && raw !== '[]') {
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length > 0) {
        return arr.slice(0, EXPLORE_SECTION_CARDS_MAX).map((row) => (row && typeof row === 'object' ? row : {}));
      }
    } catch (e) {
      console.error('exploreCommunitiesCards parse', e);
    }
  }
  return [
    {
      gallery_key: 'procter',
      title: data.aboutProcterTitle || 'Procter Village',
      distance: data.aboutProcterDistance || 'In the same village',
      description: data.aboutProcterDescription || '',
      hero_image_url: data.aboutProcterImageUrl || '',
      gallery: parseGalleryJson(data.aboutProcterGallery)
    },
    {
      gallery_key: 'nelson',
      title: data.aboutNelsonTitle || 'Nelson City',
      distance: data.aboutNelsonDistance || '35 km from Back to Base',
      description: data.aboutNelsonDescription || '',
      hero_image_url: data.aboutNelsonImageUrl || '',
      gallery: parseGalleryJson(data.aboutNelsonGallery)
    },
    {
      gallery_key: 'kaslo',
      title: data.aboutKasloTitle || 'Kaslo Village',
      distance: data.aboutKasloDistance || 'About 70 km north along Kootenay Lake',
      description: data.aboutKasloDescription || '',
      hero_image_url: data.aboutKasloImageUrl || '',
      gallery: parseGalleryJson(data.aboutKasloGallery)
    },
    {
      gallery_key: 'crawford',
      title: data.aboutCrawfordTitle || 'Crawford Bay Village',
      distance: data.aboutCrawfordDistance || 'East shore of Kootenay Lake',
      description: data.aboutCrawfordDescription || '',
      hero_image_url: data.aboutCrawfordImageUrl || '',
      gallery: parseGalleryJson(data.aboutCrawfordGallery)
    }
  ];
}

function defaultExploreCultureCardsFromApi(data) {
  const raw = data && data.exploreCultureCards != null ? String(data.exploreCultureCards).trim() : '';
  if (raw !== '' && raw !== '[]') {
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length > 0) {
        return arr.slice(0, EXPLORE_SECTION_CARDS_MAX).map((row) => (row && typeof row === 'object' ? row : {}));
      }
    } catch (e) {
      console.error('exploreCultureCards parse', e);
    }
  }
  return [
    {
      gallery_key: 'museum',
      title: data.aboutMuseumTitle || 'Touchstones Nelson',
      distance: data.aboutMuseumDistance || 'In downtown Nelson',
      description: data.aboutMuseumDescription || '',
      hero_image_url: data.aboutMuseumImageUrl || '',
      gallery: parseGalleryJson(data.aboutMuseumGallery)
    }
  ];
}

function defaultExploreActivitiesCardsFromApi(data) {
  const raw = data && data.exploreActivitiesCards != null ? String(data.exploreActivitiesCards).trim() : '';
  if (raw !== '' && raw !== '[]') {
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length > 0) {
        return arr.slice(0, EXPLORE_SECTION_CARDS_MAX).map((row) => (row && typeof row === 'object' ? row : {}));
      }
    } catch (e) {
      console.error('exploreActivitiesCards parse', e);
    }
  }
  return [
    {
      gallery_key: 'halcyon',
      title: data.aboutHalcyonTitle || 'Ainsworth Hot Springs Resort',
      distance: data.aboutHalcyonDistance || '30 km from Back to Base',
      description: data.aboutHalcyonDescription || '',
      hero_image_url: data.aboutHalcyonImageUrl || '',
      gallery: parseGalleryJson(data.aboutHalcyonGallery)
    },
    {
      gallery_key: 'whitewater',
      title: data.aboutWhitewaterTitle || 'Whitewater Mountain Resort',
      distance: data.aboutWhitewaterDistance || '60 km from Back to Base',
      description: data.aboutWhitewaterDescription || '',
      hero_image_url: data.aboutWhitewaterImageUrl || '',
      gallery: parseGalleryJson(data.aboutWhitewaterGallery)
    }
  ];
}

function appendExploreSectionCardHiddenFields(section, slot) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  const hid = document.getElementById(cfg.hidRootId);
  if (!hid) return;
  const wrap = document.createElement('div');
  wrap.className = 'explore-section-card-hidden-slot';
  wrap.setAttribute('data-section', section);
  wrap.setAttribute('data-slot', String(slot));
  wrap.innerHTML = `
    <input type="hidden" id="explore-${cfg.uploadSlug}-card-${slot}-hero-url" value="">
    <input type="hidden" id="explore-${cfg.uploadSlug}-card-${slot}-gallery" value="[]">
    <input type="hidden" id="explore-${cfg.uploadSlug}-card-${slot}-gallery-key" value="">
    <input type="file" id="explore-${cfg.uploadSlug}-card-${slot}-hero-upload" accept="image/*" style="display: none;">
    <input type="file" id="explore-${cfg.uploadSlug}-card-${slot}-gallery-upload" accept="image/*" multiple style="display: none;">
  `;
  hid.appendChild(wrap);
}

function createExploreSectionCardAdminRow(section, slot, total) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return document.createElement('div');
  const slug = cfg.uploadSlug;
  const row = document.createElement('div');
  row.className = 'preview-block explore-section-card-admin-row';
  row.setAttribute('data-explore-section', section);
  row.setAttribute('data-section-slot', String(slot));
  const disableRemove = total <= 1;
  row.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <span style="font-size:0.72rem;color:#64748b;">Card ${slot}</span>
      <button type="button" class="explore-section-card-remove-btn" data-explore-section="${section}" data-section-slot="${slot}"
        ${disableRemove ? 'disabled style="opacity:0.4;cursor:not-allowed;padding:2px 8px;font-size:0.7rem;border:1px solid #e5e7eb;border-radius:4px;background:#f9fafb;"' : 'style="padding:2px 8px;font-size:0.7rem;border:1px solid #fecaca;border-radius:4px;background:#fef2f2;color:#b91c1c;cursor:pointer;"'}>Remove</button>
    </div>
    <div class="preview-block-content" style="display: grid; grid-template-columns: 96px 1fr; gap: 8px; align-items: start;">
      <div class="preview-image" style="width: 96px; height: 72px; background: #f3f4f6; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; cursor: pointer;" onmouseenter="showImageEditButton(this)" onmouseleave="hideImageEditButton(this)" onclick="triggerImageUpload('explore-${slug}-card-${slot}-hero-upload')">
        <img id="preview-explore-${slug}-card-${slot}-hero-img" src="" alt="" style="max-width: 100%; max-height: 100%; object-fit: cover; display: none;">
        <span style="color: #9ca3af; font-size: 0.6rem; text-align: center; padding: 4px;">Photo</span>
        <button type="button" class="image-edit-btn" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 0.65rem; z-index: 10;">Edit</button>
      </div>
      <div class="preview-text" style="display: flex; flex-direction: column; gap: 4px;">
        <div contenteditable="true" class="editable-preview explore-section-card-field" id="preview-explore-${slug}-card-${slot}-title" style="font-weight: 600; color: #0f172a; font-size: 0.78rem; padding: 2px 6px; border-radius: 4px; outline: none; background: #fff; border: 1px solid #e2e8f0;" onblur="onExploreSectionCardBlur('${section}')"></div>
        <div contenteditable="true" class="editable-preview explore-section-card-field" id="preview-explore-${slug}-card-${slot}-distance" style="color: #64748b; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; outline: none; background: #fff; border: 1px solid #e2e8f0;" onblur="onExploreSectionCardBlur('${section}')"></div>
        <div contenteditable="true" class="editable-preview explore-section-card-field" id="preview-explore-${slug}-card-${slot}-description" style="color: #0f172a; font-size: 0.7rem; line-height: 1.45; min-height: 2.2em; padding: 4px 6px; border-radius: 4px; outline: none; background: #fff; border: 1px solid #e2e8f0;" onblur="onExploreSectionCardBlur('${section}')"></div>
      </div>
    </div>
    <p style="margin: 8px 0 2px 0; font-size: 0.68rem; color: #6b7280; font-weight: 600;">Gallery</p>
    <div id="explore-${slug}-card-${slot}-gallery-preview" style="display: flex; flex-wrap: wrap; gap: 6px; min-height: 14px;"></div>
  `;
  return row;
}

function readExploreSectionCardsFromAdmin(section) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return [];
  const root = document.getElementById(cfg.rootId);
  if (!root) return [];
  const rows = Array.from(root.querySelectorAll('.explore-section-card-admin-row')).sort((a, b) => {
    const sa = parseInt(a.getAttribute('data-section-slot'), 10);
    const sb = parseInt(b.getAttribute('data-section-slot'), 10);
    return (Number.isFinite(sa) ? sa : 0) - (Number.isFinite(sb) ? sb : 0);
  });
  const cards = [];
  rows.forEach((row) => {
    const n = parseInt(row.getAttribute('data-section-slot'), 10);
    if (!Number.isFinite(n)) return;
    const slug = cfg.uploadSlug;
    const titleEl = document.getElementById(`preview-explore-${slug}-card-${n}-title`);
    const distEl = document.getElementById(`preview-explore-${slug}-card-${n}-distance`);
    const descEl = document.getElementById(`preview-explore-${slug}-card-${n}-description`);
    const galEl = document.getElementById(`explore-${slug}-card-${n}-gallery`);
    const heroHid = document.getElementById(`explore-${slug}-card-${n}-hero-url`);
    const gkEl = document.getElementById(`explore-${slug}-card-${n}-gallery-key`);
    let gallery = [];
    try {
      gallery = JSON.parse(galEl ? galEl.value || '[]' : '[]');
    } catch (e) {
      gallery = [];
    }
    let gallery_key = gkEl ? String(gkEl.value || '').trim() : '';
    if (!gallery_key) {
      gallery_key = newExploreGalleryKey();
      if (gkEl) gkEl.value = gallery_key;
    }
    cards.push({
      gallery_key,
      title: titleEl ? String(titleEl.textContent || '').trim() : '',
      distance: distEl ? String(distEl.textContent || '').trim() : '',
      description: descEl ? String(descEl.innerHTML || '').trim() : '',
      hero_image_url: heroHid ? String(heroHid.value || '').trim() : '',
      gallery: Array.isArray(gallery) ? gallery : []
    });
  });
  return cards;
}

function writeExploreSectionCardsToAdmin(section, cards) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  let list = Array.isArray(cards) ? cards.slice(0, EXPLORE_SECTION_CARDS_MAX) : [];
  if (list.length === 0) {
    list = [{ gallery_key: newExploreGalleryKey(), title: '', distance: '', description: '', hero_image_url: '', gallery: [] }];
  }
  const ta = document.getElementById(cfg.jsonId);
  if (ta) ta.value = JSON.stringify(list);
  const root = document.getElementById(cfg.rootId);
  const hidRoot = document.getElementById(cfg.hidRootId);
  if (!root || !hidRoot) return;
  root.innerHTML = '';
  hidRoot.innerHTML = '';
  const total = list.length;
  list.forEach((c, idx) => {
    const slot = idx + 1;
    appendExploreSectionCardHiddenFields(section, slot);
    root.appendChild(createExploreSectionCardAdminRow(section, slot, total));
  });
  list.forEach((c, idx) => {
    const n = idx + 1;
    const slug = cfg.uploadSlug;
    const titleEl = document.getElementById(`preview-explore-${slug}-card-${n}-title`);
    const distEl = document.getElementById(`preview-explore-${slug}-card-${n}-distance`);
    const descEl = document.getElementById(`preview-explore-${slug}-card-${n}-description`);
    const galEl = document.getElementById(`explore-${slug}-card-${n}-gallery`);
    const heroHid = document.getElementById(`explore-${slug}-card-${n}-hero-url`);
    const gkEl = document.getElementById(`explore-${slug}-card-${n}-gallery-key`);
    let gk = (c.gallery_key && String(c.gallery_key).trim()) ? String(c.gallery_key).trim() : newExploreGalleryKey();
    gk = gk.replace(/[^a-zA-Z0-9_]/g, '');
    if (!gk) gk = newExploreGalleryKey();
    if (gkEl) gkEl.value = gk;
    if (titleEl) titleEl.textContent = c.title || '';
    if (distEl) distEl.textContent = c.distance || '';
    if (descEl) descEl.innerHTML = c.description || '';
    const heroUrl = (c.hero_image_url && String(c.hero_image_url).trim()) ? String(c.hero_image_url).trim() : '';
    if (heroHid) heroHid.value = heroUrl;
    if (galEl) galEl.value = JSON.stringify(Array.isArray(c.gallery) ? c.gallery : []);
    const img = document.getElementById(`preview-explore-${slug}-card-${n}-hero-img`);
    if (img) {
      if (heroUrl) {
        img.src = heroUrl + '?v=' + Date.now();
        img.style.display = 'block';
        const placeholder = img.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
      } else {
        img.removeAttribute('src');
        img.style.display = 'none';
        const placeholder = img.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = '';
      }
    }
    updateExploreSectionCardGalleryPreview(section, n, Array.isArray(c.gallery) ? c.gallery : []);
  });
}

window.onExploreSectionCardBlur = function onExploreSectionCardBlur(section) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  const ta = document.getElementById(cfg.jsonId);
  if (ta) {
    ta.value = JSON.stringify(readExploreSectionCardsFromAdmin(section));
  }
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

window.addExploreSectionCardRow = function addExploreSectionCardRow(section) {
  const cards = readExploreSectionCardsFromAdmin(section);
  if (cards.length >= EXPLORE_SECTION_CARDS_MAX) {
    alert(`Maximum ${EXPLORE_SECTION_CARDS_MAX} cards for this section.`);
    return;
  }
  cards.push({
    gallery_key: newExploreGalleryKey(),
    title: '',
    distance: '',
    description: '',
    hero_image_url: '',
    gallery: []
  });
  writeExploreSectionCardsToAdmin(section, cards);
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

window.removeExploreSectionCardRow = function removeExploreSectionCardRow(section, slot) {
  const cards = readExploreSectionCardsFromAdmin(section);
  if (cards.length <= 1) return;
  const idx = slot - 1;
  if (idx < 0 || idx >= cards.length) return;
  cards.splice(idx, 1);
  writeExploreSectionCardsToAdmin(section, cards);
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

function updateExploreSectionCardGalleryPreview(section, slot, gallery) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  const slug = cfg.uploadSlug;
  const galleryPreview = document.getElementById(`explore-${slug}-card-${slot}-gallery-preview`);
  if (!galleryPreview) return;
  galleryPreview.innerHTML = '';
  const urls = Array.isArray(gallery) ? gallery : [];
  urls.forEach((imageUrl, index) => {
    if (!imageUrl || !String(imageUrl).trim()) return;
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 56px; height: 56px; border: 2px solid #e5e7eb; border-radius: 6px; overflow: hidden; background: #f3f4f6;';
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = '↻';
    replaceBtn.type = 'button';
    replaceBtn.style.cssText = 'position: absolute; top: 1px; left: 1px; padding: 1px 4px; font-size: 0.65rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 3px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceExploreSectionCardGalleryImage(section, slot, index);
    };
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.type = 'button';
    deleteBtn.style.cssText = 'position: absolute; top: 1px; right: 1px; width: 18px; height: 18px; padding: 0; font-size: 0.85rem; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteExploreSectionCardGalleryImage(section, slot, index);
    };
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  if (urls.length < 10) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 56px; height: 56px; border: 2px dashed #9ca3af; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 1.2rem;">+</span>';
    addItem.onclick = () => document.getElementById(`explore-${slug}-card-${slot}-gallery-upload`).click();
    galleryPreview.appendChild(addItem);
  }
}

window.replaceExploreSectionCardGalleryImage = function replaceExploreSectionCardGalleryImage(section, slot, index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadExploreSectionCardGalleryImage(section, slot, file, index);
    }
  };
  input.click();
};

window.deleteExploreSectionCardGalleryImage = function deleteExploreSectionCardGalleryImage(section, slot, index) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  const slug = cfg.uploadSlug;
  const galleryField = document.getElementById(`explore-${slug}-card-${slot}-gallery`);
  if (!galleryField) return;
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    return;
  }
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateExploreSectionCardGalleryPreview(section, slot, gallery);
  onExploreSectionCardBlur(section);
};

async function uploadExploreSectionCardGalleryImage(section, slot, file, replaceIndex = null) {
  const cfg = EXPLORE_SECTION_CARD_META[section];
  if (!cfg) return;
  const slug = cfg.uploadSlug;
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', `explore-${slug}-card-${slot}-gallery`);
    formData.append('image', file);
    const response = await fetch('upload_image.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (!result.success || !result.imageUrl) return;
    const imageUrl = result.imageUrl;
    const galleryField = document.getElementById(`explore-${slug}-card-${slot}-gallery`);
    if (!galleryField) return;
    let gallery = [];
    try {
      gallery = JSON.parse(galleryField.value || '[]');
    } catch (e) {
      gallery = [];
    }
    if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
      gallery[replaceIndex] = imageUrl;
    } else if (gallery.length < 10) {
      gallery.push(imageUrl);
    } else {
      alert('Maximum 10 photos allowed in gallery');
      return;
    }
    galleryField.value = JSON.stringify(gallery);
    updateExploreSectionCardGalleryPreview(section, slot, gallery);
    const cards = readExploreSectionCardsFromAdmin(section);
    const fd = new FormData();
    fd.append('action', 'save_content');
    const postKey = section === 'communities' ? 'explore_communities_cards' : section === 'culture' ? 'explore_culture_cards' : 'explore_activities_cards';
    fd.append(postKey, JSON.stringify(cards));
    const saveResp = await fetch('api.php', { method: 'POST', body: fd });
    const saveTxt = await saveResp.text();
    recordSaveContentResponse(saveResp, saveTxt, 'explore');
    if (typeof scheduleExploreAutoSave === 'function') {
      exploreHasUnsavedChanges = true;
      scheduleExploreAutoSave();
    }
  } catch (err) {
    console.error('uploadExploreSectionCardGalleryImage', err);
  }
}

let exploreSectionCardsToolbarInit = false;
function initExploreSectionCardsToolbar() {
  if (exploreSectionCardsToolbarInit) return;
  exploreSectionCardsToolbarInit = true;
  const map = [
    ['explore-add-communities-card-btn', 'communities'],
    ['explore-add-culture-card-btn', 'culture'],
    ['explore-add-activities-card-btn', 'activities']
  ];
  map.forEach(([btnId, sec]) => {
    const b = document.getElementById(btnId);
    if (b) {
      b.addEventListener('click', () => {
        if (typeof window.addExploreSectionCardRow === 'function') {
          window.addExploreSectionCardRow(sec);
        }
      });
    }
  });
  ['communities', 'culture', 'activities'].forEach((sec) => {
    const cfg = EXPLORE_SECTION_CARD_META[sec];
    const root = cfg ? document.getElementById(cfg.rootId) : null;
    if (root) {
      root.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest ? e.target.closest('.explore-section-card-remove-btn') : null;
        if (!btn || btn.disabled) return;
        const s = parseInt(btn.getAttribute('data-section-slot'), 10);
        if (Number.isFinite(s) && typeof window.removeExploreSectionCardRow === 'function') {
          window.removeExploreSectionCardRow(sec, s);
        }
      });
    }
  });
}

let exploreSectionGalleryDelegationBound = false;
function initExploreSectionCardGalleryUploads() {
  const section = document.getElementById('explore-section');
  if (!section || exploreSectionGalleryDelegationBound) return;
  exploreSectionGalleryDelegationBound = true;
  section.addEventListener('change', async (e) => {
    const t = e.target;
    if (!t || !t.id) return;
    const m = t.id.match(/^explore-(communities|culture|activities)-card-(\d+)-gallery-upload$/);
    if (!m || t.type !== 'file') return;
    const sec = m[1] === 'communities' ? 'communities' : m[1] === 'culture' ? 'culture' : 'activities';
    const slot = parseInt(m[2], 10);
    const files = Array.from(t.files || []);
    if (files.length === 0) return;
    const slug = EXPLORE_SECTION_CARD_META[sec].uploadSlug;
    const galleryField = document.getElementById(`explore-${slug}-card-${slot}-gallery`);
    if (!galleryField) return;
    let gallery = [];
    try {
      gallery = JSON.parse(galleryField.value || '[]');
    } catch (err) {
      gallery = [];
    }
    const remainingSlots = 10 - gallery.length;
    if (files.length > remainingSlots) {
      alert(`You can only add ${remainingSlots} more photo(s).`);
      files.splice(remainingSlots);
    }
    for (const file of files) {
      await uploadExploreSectionCardGalleryImage(sec, slot, file, null);
    }
    t.value = '';
  });
}

let exploreSectionHeroDelegationBound = false;
function initExploreSectionCardHeroUploadDelegation() {
  const section = document.getElementById('explore-section');
  if (!section || exploreSectionHeroDelegationBound) return;
  exploreSectionHeroDelegationBound = true;
  section.addEventListener('change', async (e) => {
    const t = e.target;
    if (!t || !t.id) return;
    const m = t.id.match(/^explore-(communities|culture|activities)-card-(\d+)-hero-upload$/);
    if (!m || t.type !== 'file') return;
    const sec = m[1] === 'communities' ? 'communities' : m[1] === 'culture' ? 'culture' : 'activities';
    const slot = parseInt(m[2], 10);
    const file = t.files[0];
    if (!file) return;
    const slug = EXPLORE_SECTION_CARD_META[sec].uploadSlug;
    const previewImg = document.getElementById(`preview-explore-${slug}-card-${slot}-hero-img`);
    if (previewImg) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        previewImg.src = ev.target.result;
        previewImg.style.display = 'block';
        const placeholder = previewImg.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
      };
      reader.readAsDataURL(file);
    }
    await uploadImage(file, `explore-${slug}-card-${slot}-hero`, null, null, {
      localStorageKey: 'btb_explore_images',
      fieldNameMapper: (type) => {
        const mm = String(type).match(/^explore-(communities|culture|activities)-card-(\d+)-hero$/);
        if (mm) return `secCard_${mm[1]}_${mm[2]}`;
        return type;
      },
      reloadFunction: loadExploreImagesData,
      imageNameMapper: (type) => type,
      onSuccess: (filepath) => {
        const hid = document.getElementById(`explore-${slug}-card-${slot}-hero-url`);
        if (hid) hid.value = filepath;
        const cards = readExploreSectionCardsFromAdmin(sec);
        if (cards[slot - 1]) cards[slot - 1].hero_image_url = filepath;
        writeExploreSectionCardsToAdmin(sec, cards);
        const fd = new FormData();
        fd.append('action', 'save_content');
        const postKey = sec === 'communities' ? 'explore_communities_cards' : sec === 'culture' ? 'explore_culture_cards' : 'explore_activities_cards';
        fd.append(postKey, JSON.stringify(cards));
        fetch('api.php', { method: 'POST', body: fd })
          .then((r) => r.text().then((txt) => {
            recordSaveContentResponse(r, txt, 'explore');
          }))
          .catch((err) => console.error('save section cards hero', err));
        if (typeof scheduleExploreAutoSave === 'function') {
          scheduleExploreAutoSave();
        }
      }
    });
    t.value = '';
  });
}

function defaultExploreParkCardsFromApi(data) {
  const defaultNames = [
    'Kokanee Creek Provincial Park',
    'Kokanee Glacier Provincial Park',
    'Lockhart Beach Provincial Park',
    'Kianuko Provincial Park'
  ];
  const raw = data && data.aboutParksCards != null ? String(data.aboutParksCards).trim() : '';
  if (raw !== '' && raw !== '[]') {
    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length > 0) {
        const out = [];
        const limit = Math.min(arr.length, EXPLORE_PARK_CARDS_MAX);
        for (let i = 0; i < limit; i++) {
          const row = arr[i] && typeof arr[i] === 'object' ? arr[i] : {};
          out.push({
            title: typeof row.title === 'string' ? row.title : (defaultNames[i] || ''),
            description: typeof row.description === 'string' ? row.description : '',
            hero_image_url: typeof row.hero_image_url === 'string' ? row.hero_image_url : '',
            gallery: Array.isArray(row.gallery) ? row.gallery.filter((u) => typeof u === 'string' && u.trim()) : [],
            map_lat: typeof row.map_lat === 'string' ? row.map_lat : (row.map_lat != null ? String(row.map_lat) : ''),
            map_lng: typeof row.map_lng === 'string' ? row.map_lng : (row.map_lng != null ? String(row.map_lng) : '')
          });
        }
        return out;
      }
    } catch (e) {
      console.error('defaultExploreParkCardsFromApi parse error', e);
    }
  }
  const listSrc = (data && data.aboutParksList && String(data.aboutParksList).trim())
    ? data.aboutParksList
    : defaultNames.join('\n');
  const titles = listSrc.split('\n').map((l) => l.trim()).filter(Boolean);
  while (titles.length < 4) {
    titles.push(defaultNames[titles.length] || `Park ${titles.length + 1}`);
  }
  return titles.slice(0, 4).map((title) => ({
    title,
    description: '',
    hero_image_url: '',
    gallery: [],
    map_lat: '',
    map_lng: ''
  }));
}

function appendExploreParkCardHiddenFields(slot) {
  const hidRoot = document.getElementById('explore-park-cards-hidden-fields');
  if (!hidRoot) return;
  const wrap = document.createElement('div');
  wrap.className = 'explore-park-card-hidden-slot';
  wrap.dataset.parkSlot = String(slot);
  wrap.innerHTML = `
    <input type="hidden" id="about-park-card-${slot}-gallery" value="[]">
    <input type="hidden" id="about-park-card-${slot}-hero-url" value="">
    <input type="file" id="about-park-card-${slot}-hero-upload" accept="image/*" style="display: none;">
    <input type="file" id="about-park-card-${slot}-gallery-upload" accept="image/*" multiple style="display: none;">
  `;
  hidRoot.appendChild(wrap);
}

function createExploreParkCardAdminRow(slot, totalCards) {
  const canRemove = totalCards > 1;
  const row = document.createElement('div');
  row.className = 'preview-block explore-park-card-admin-row';
  row.style.cssText = 'margin: 0; padding: 12px; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px;';
  row.setAttribute('data-park-slot', String(slot));
  row.innerHTML = `
    <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 8px; gap: 8px;">
      <div style="font-weight: 600; color: #475569; font-size: 0.75rem;">Park card ${slot}</div>
      <button type="button" class="explore-park-card-remove-btn" data-park-slot="${slot}"
        style="font-size: 0.7rem; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: ${canRemove ? 'pointer' : 'not-allowed'}; opacity: ${canRemove ? 1 : 0.45};"
        ${canRemove ? '' : 'disabled'}>Remove</button>
    </div>
    <div style="display: grid; grid-template-columns: 96px 1fr; gap: 8px; align-items: start;">
      <div class="preview-image" style="width: 96px; height: 72px; background: #f3f4f6; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; cursor: pointer;" onmouseenter="showImageEditButton(this)" onmouseleave="hideImageEditButton(this)" onclick="triggerImageUpload('about-park-card-${slot}-hero-upload')">
        <img id="preview-about-park-card-${slot}-hero-img" src="" alt="" style="max-width: 100%; max-height: 100%; object-fit: cover; display: none;">
        <span style="color: #9ca3af; font-size: 0.6rem; text-align: center; padding: 4px;">Hero</span>
        <button type="button" class="image-edit-btn" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 0.65rem; z-index: 10;">Edit</button>
      </div>
      <div style="display: flex; flex-direction: column; gap: 4px;">
        <div contenteditable="true" class="editable-preview explore-park-card-field" id="preview-park-card-${slot}-title" style="font-weight: 600; color: #0f172a; font-size: 0.78rem; padding: 2px 6px; border-radius: 4px; outline: none; background: #fff; border: 1px solid #e2e8f0;" onblur="onExploreParkCardBlur()"></div>
        <div contenteditable="true" class="editable-preview explore-park-card-field" id="preview-park-card-${slot}-description" style="color: #0f172a; font-size: 0.7rem; line-height: 1.45; min-height: 2.2em; padding: 4px 6px; border-radius: 4px; outline: none; background: #fff; border: 1px solid #e2e8f0;" onblur="onExploreParkCardBlur()"></div>
        <label style="display: block; font-size: 0.68rem; color: #64748b; margin-top: 2px;">Map coordinates
          <input type="text" class="explore-park-card-field" id="preview-park-card-${slot}-map-coords" placeholder="49.592, -117.088" style="width: 100%; max-width: 200px; margin-top: 2px; padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.68rem; box-sizing: border-box; background: #fff; color: #000; -webkit-text-fill-color: #000;" onblur="onExploreParkCardBlur()">
        </label>
      </div>
    </div>
    <p style="margin: 8px 0 4px 0; font-size: 0.68rem; color: #6b7280; font-weight: 600;">Gallery</p>
    <div id="about-park-card-${slot}-gallery-preview" style="display: flex; flex-wrap: wrap; gap: 6px; min-height: 14px;"></div>
  `;
  return row;
}

function readExploreParkCardsFromAdmin() {
  const root = document.getElementById('explore-park-cards-admin-root');
  if (!root) return [];
  const rows = root.querySelectorAll('.explore-park-card-admin-row');
  const cards = [];
  rows.forEach((row) => {
    const n = parseInt(row.getAttribute('data-park-slot'), 10);
    if (!Number.isFinite(n)) return;
    const titleEl = document.getElementById(`preview-park-card-${n}-title`);
    const descEl = document.getElementById(`preview-park-card-${n}-description`);
    const coordsEl = document.getElementById(`preview-park-card-${n}-map-coords`);
    const galEl = document.getElementById(`about-park-card-${n}-gallery`);
    const heroHid = document.getElementById(`about-park-card-${n}-hero-url`);
    const { map_lat: ml, map_lng: mg } = parseParkCardMapCoordsInput(coordsEl ? coordsEl.value : '');
    let gallery = [];
    try {
      gallery = JSON.parse(galEl ? galEl.value || '[]' : '[]');
    } catch (e) {
      gallery = [];
    }
    cards.push({
      title: titleEl ? String(titleEl.textContent || '').trim() : '',
      description: descEl ? String(descEl.innerHTML || '').trim() : '',
      hero_image_url: heroHid ? String(heroHid.value || '').trim() : '',
      gallery: Array.isArray(gallery) ? gallery : [],
      map_lat: ml,
      map_lng: mg
    });
  });
  return cards;
}

function writeExploreParkCardsToAdmin(cards) {
  let list = Array.isArray(cards) ? cards.slice(0, EXPLORE_PARK_CARDS_MAX) : [];
  if (list.length === 0) {
    list = [{
      title: '',
      description: '',
      hero_image_url: '',
      gallery: [],
      map_lat: '',
      map_lng: ''
    }];
  }
  const ta = document.getElementById('about-parks-cards-json');
  if (ta) {
    ta.value = JSON.stringify(list);
  }
  const root = document.getElementById('explore-park-cards-admin-root');
  const hidRoot = document.getElementById('explore-park-cards-hidden-fields');
  if (!root || !hidRoot) return;
  root.innerHTML = '';
  hidRoot.innerHTML = '';
  const total = list.length;
  list.forEach((c, idx) => {
    const slot = idx + 1;
    appendExploreParkCardHiddenFields(slot);
    root.appendChild(createExploreParkCardAdminRow(slot, total));
  });
  list.forEach((c, idx) => {
    const n = idx + 1;
    const titleEl = document.getElementById(`preview-park-card-${n}-title`);
    const descEl = document.getElementById(`preview-park-card-${n}-description`);
    const coordsEl = document.getElementById(`preview-park-card-${n}-map-coords`);
    const galEl = document.getElementById(`about-park-card-${n}-gallery`);
    const heroHid = document.getElementById(`about-park-card-${n}-hero-url`);
    const img = document.getElementById(`preview-about-park-card-${n}-hero-img`);
    if (titleEl) titleEl.textContent = c.title || '';
    if (descEl) descEl.innerHTML = c.description || '';
    if (coordsEl) coordsEl.value = formatParkCardMapCoords(c.map_lat, c.map_lng);
    const heroUrl = (c.hero_image_url && String(c.hero_image_url).trim()) ? String(c.hero_image_url).trim() : '';
    if (heroHid) heroHid.value = heroUrl;
    if (galEl) galEl.value = JSON.stringify(Array.isArray(c.gallery) ? c.gallery : []);
    if (img) {
      if (heroUrl) {
        img.src = heroUrl + '?v=' + Date.now();
        img.style.display = 'block';
        const placeholder = img.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
      } else {
        img.removeAttribute('src');
        img.style.display = 'none';
        const placeholder = img.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = '';
      }
    }
    updateParkCardGalleryPreview(n, Array.isArray(c.gallery) ? c.gallery : []);
  });
}

window.addExploreParkCardRow = function addExploreParkCardRow() {
  const cards = readExploreParkCardsFromAdmin();
  if (cards.length >= EXPLORE_PARK_CARDS_MAX) {
    alert(`Maximum ${EXPLORE_PARK_CARDS_MAX} park cards.`);
    return;
  }
  cards.push({
    title: '',
    description: '',
    hero_image_url: '',
    gallery: [],
    map_lat: '',
    map_lng: ''
  });
  writeExploreParkCardsToAdmin(cards);
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

window.removeExploreParkCardRow = function removeExploreParkCardRow(slot) {
  const cards = readExploreParkCardsFromAdmin();
  if (cards.length <= 1) return;
  const idx = slot - 1;
  if (idx < 0 || idx >= cards.length) return;
  cards.splice(idx, 1);
  writeExploreParkCardsToAdmin(cards);
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

let exploreParkCardsToolbarInit = false;
function initExploreParkCardsToolbar() {
  if (exploreParkCardsToolbarInit) return;
  exploreParkCardsToolbarInit = true;
  const addBtn = document.getElementById('explore-add-park-card-btn');
  if (addBtn) {
    addBtn.addEventListener('click', () => {
      if (typeof window.addExploreParkCardRow === 'function') {
        window.addExploreParkCardRow();
      }
    });
  }
  const root = document.getElementById('explore-park-cards-admin-root');
  if (root) {
    root.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest ? e.target.closest('.explore-park-card-remove-btn') : null;
      if (!btn || btn.disabled) return;
      const s = parseInt(btn.getAttribute('data-park-slot'), 10);
      if (Number.isFinite(s) && typeof window.removeExploreParkCardRow === 'function') {
        window.removeExploreParkCardRow(s);
      }
    });
  }
}

function persistExploreParkCardsToServer() {
  const cards = readExploreParkCardsFromAdmin();
  const listFromTitles = cards.map((c) => (c.title || '').trim()).filter(Boolean).join('\n');
  const fd = new FormData();
  fd.append('action', 'save_content');
  fd.append('about_parks_cards', JSON.stringify(cards));
  fd.append('about_parks_list', listFromTitles);
  return fetch('api.php', { method: 'POST', body: fd }).then((r) =>
    r.text().then((txt) => {
      recordSaveContentResponse(r, txt, 'explore');
      return { response: r, text: txt };
    })
  );
}

window.onExploreParkCardBlur = function onExploreParkCardBlur() {
  const ta = document.getElementById('about-parks-cards-json');
  if (ta) {
    ta.value = JSON.stringify(readExploreParkCardsFromAdmin());
  }
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

function updateParkCardGalleryPreview(slot, gallery) {
  const galleryPreview = document.getElementById(`about-park-card-${slot}-gallery-preview`);
  if (!galleryPreview) return;
  galleryPreview.innerHTML = '';
  const urls = Array.isArray(gallery) ? gallery : [];
  urls.forEach((imageUrl, index) => {
    if (!imageUrl || !String(imageUrl).trim()) return;
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 56px; height: 56px; border: 2px solid #e5e7eb; border-radius: 6px; overflow: hidden; background: #f3f4f6;';
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = '↻';
    replaceBtn.type = 'button';
    replaceBtn.style.cssText = 'position: absolute; top: 1px; left: 1px; padding: 1px 4px; font-size: 0.65rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 3px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceParkCardGalleryImage(slot, index);
    };
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.type = 'button';
    deleteBtn.style.cssText = 'position: absolute; top: 1px; right: 1px; width: 16px; height: 16px; padding: 0; font-size: 0.75rem; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; line-height: 1;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteParkCardGalleryImage(slot, index);
    };
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  if (urls.length < 10) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 56px; height: 56px; border: 2px dashed #9ca3af; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 1.1rem;">+</span>';
    addItem.onclick = () => {
      const up = document.getElementById(`about-park-card-${slot}-gallery-upload`);
      if (up) up.click();
    };
    galleryPreview.appendChild(addItem);
  }
}

window.replaceParkCardGalleryImage = function replaceParkCardGalleryImage(slot, index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadParkCardGalleryImage(slot, file, index);
    }
  };
  input.click();
};

window.deleteParkCardGalleryImage = function deleteParkCardGalleryImage(slot, index) {
  const galleryField = document.getElementById(`about-park-card-${slot}-gallery`);
  if (!galleryField) return;
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    return;
  }
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateParkCardGalleryPreview(slot, gallery);
  if (typeof exploreHasUnsavedChanges !== 'undefined') {
    exploreHasUnsavedChanges = true;
  }
  if (typeof scheduleExploreAutoSave === 'function') {
    scheduleExploreAutoSave();
  }
};

async function uploadParkCardGalleryImage(slot, file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', `about-park-card-${slot}-gallery`);
    formData.append('image', file);
    const response = await fetch('upload_image.php', { method: 'POST', body: formData });
    if (!response.ok) return;
    const result = await response.json();
    const payload = result && result.data ? result.data : result;
    const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : '');
    if (!result.success || !imageUrl) return;
    const galleryField = document.getElementById(`about-park-card-${slot}-gallery`);
    if (!galleryField) return;
    let gallery = [];
    try {
      gallery = JSON.parse(galleryField.value || '[]');
    } catch (e) {
      gallery = [];
    }
    if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
      gallery[replaceIndex] = imageUrl;
    } else if (gallery.length < 10) {
      gallery.push(imageUrl);
    } else {
      alert('Maximum 10 photos allowed in gallery');
      return;
    }
    galleryField.value = JSON.stringify(gallery);
    updateParkCardGalleryPreview(slot, gallery);
    const cards = readExploreParkCardsFromAdmin();
    const fd = new FormData();
    fd.append('action', 'save_content');
    fd.append('about_parks_cards', JSON.stringify(cards));
    const listFromTitles = cards.map((c) => (c.title || '').trim()).filter(Boolean).join('\n');
    fd.append('about_parks_list', listFromTitles);
    const pr = await fetch('api.php', { method: 'POST', body: fd });
    const ptxt = await pr.text();
    recordSaveContentResponse(pr, ptxt, 'explore');
  } catch (err) {
    console.error('uploadParkCardGalleryImage', err);
  }
}

let exploreParkGalleryUploadDelegationBound = false;
function initParkCardGalleryUploads() {
  const section = document.getElementById('explore-section');
  if (!section || exploreParkGalleryUploadDelegationBound) return;
  exploreParkGalleryUploadDelegationBound = true;
  section.addEventListener('change', async (e) => {
    const t = e.target;
    if (!t || !t.id) return;
    const m = t.id.match(/^about-park-card-(\d+)-gallery-upload$/);
    if (!m || t.type !== 'file') return;
    const slot = parseInt(m[1], 10);
    const files = Array.from(t.files || []);
    if (files.length === 0) return;
    const galleryField = document.getElementById(`about-park-card-${slot}-gallery`);
    if (!galleryField) return;
    let gallery = [];
    try {
      gallery = JSON.parse(galleryField.value || '[]');
    } catch (err) {
      gallery = [];
    }
    const remainingSlots = 10 - gallery.length;
    if (files.length > remainingSlots) {
      alert(`You can only add ${remainingSlots} more photo(s).`);
      files.splice(remainingSlots);
    }
    for (const file of files) {
      await uploadParkCardGalleryImage(slot, file, null);
    }
    t.value = '';
  });
}

/** Plain text for Explore accommodation inherited copy (from mini-hotel API fields). */
function exploreAdminPlainFromApi(s) {
  if (s == null || s === undefined) return '';
  const str = String(s);
  if (str.indexOf('<') === -1) {
    return str.replace(/\r\n/g, '\n').trim();
  }
  const d = document.createElement('div');
  d.innerHTML = str.replace(/<br\s*\/?>/gi, '\n');
  return (d.textContent || '').replace(/\r\n/g, '\n').trim();
}

// Load Explore page (hero + location intro + attraction cards + parks)
async function loadExploreData() {
  console.log('Loading Explore page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');

    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;

        const exTitle = document.getElementById('explore-hero-title');
        const exSub = document.getElementById('explore-hero-subtitle');
        const exTitleP = document.getElementById('preview-explore-hero-title');
        const exSubP = document.getElementById('preview-explore-hero-subtitle');
        if (exTitle) exTitle.value = data.exploreHeroTitle || '';
        if (exSub) exSub.value = data.exploreHeroSubtitle || '';
        if (exTitleP) exTitleP.textContent = data.exploreHeroTitle || 'Explore';
        if (exSubP) exSubP.textContent = data.exploreHeroSubtitle || 'Forests, lakes, and mountain towns around Back to Base';

        const commIntroFromApi =
          (data.exploreCommunitiesIntro && String(data.exploreCommunitiesIntro).trim() !== '')
            ? data.exploreCommunitiesIntro
            : (data.aboutAttractionsLead || '');
        const commIntroField = document.getElementById('explore-communities-intro');
        const commIntroPreview = document.getElementById('preview-explore-communities-intro');
        if (commIntroField) commIntroField.value = commIntroFromApi || '';
        if (commIntroPreview) commIntroPreview.textContent = commIntroFromApi || '';

        const culIntroField = document.getElementById('explore-culture-intro');
        const culIntroPreview = document.getElementById('preview-explore-culture-intro');
        if (culIntroField) culIntroField.value = data.exploreCultureIntro || '';
        if (culIntroPreview) culIntroPreview.textContent = data.exploreCultureIntro || '';

        const actIntroField = document.getElementById('explore-activities-intro');
        const actIntroPreview = document.getElementById('preview-explore-activities-intro');
        if (actIntroField) actIntroField.value = data.exploreActivitiesIntro || '';
        if (actIntroPreview) actIntroPreview.textContent = data.exploreActivitiesIntro || '';

        const inheritedAccDesc = (() => {
          const fromSingle = exploreAdminPlainFromApi(
            data.miniHotelDescription != null ? data.miniHotelDescription : ''
          );
          if (fromSingle) return fromSingle;
          const a = exploreAdminPlainFromApi(
            data.miniHotelDescription1 != null ? data.miniHotelDescription1 : ''
          );
          const b = exploreAdminPlainFromApi(
            data.miniHotelDescription2 != null ? data.miniHotelDescription2 : ''
          );
          if (!a && !b) return '';
          if (a && b) return a + '\n\n' + b;
          return b || a;
        })();
        const inhAcc = (window.__exploreAccommodationInherited = {});
        inhAcc['explore-accommodation-title'] = String(data.miniHotelTitle || '').trim();
        inhAcc['explore-accommodation-description'] = inheritedAccDesc;

        const exOvT = data.exploreAccommodationTitle;
        const exOvDesc = data.exploreAccommodationDescription;
        const exAccHid = document.getElementById('explore-accommodation-title');
        const exDesc = document.getElementById('explore-accommodation-description');
        if (exAccHid) {
          exAccHid.value = exOvT != null && String(exOvT).trim() !== '' ? exOvT : '';
        }
        if (exDesc) {
          exDesc.value = exOvDesc != null && String(exOvDesc).trim() !== '' ? exOvDesc : '';
        }
        const pAccT = document.getElementById('preview-explore-accommodation-title');
        const pAccDesc = document.getElementById('preview-explore-accommodation-desc');
        if (pAccT) {
          pAccT.textContent =
            exOvT != null && String(exOvT).trim() !== ''
              ? exOvT
              : (data.miniHotelTitle || 'Book a room in our mini-hotel');
        }
        if (pAccDesc) {
          pAccDesc.textContent =
            exOvDesc != null && String(exOvDesc).trim() !== ''
              ? exOvDesc
              : inheritedAccDesc;
        }

        const exploreH2Load = [
          { hid: 'explore-communities-h2', val: data.exploreCommunitiesH2, fb: 'Communities' },
          { hid: 'explore-culture-h2', val: data.exploreCultureH2, fb: 'Culture' },
          { hid: 'explore-parks-h2', val: data.exploreParksH2, fb: 'Parks and beaches' },
          { hid: 'explore-activities-h2', val: data.exploreActivitiesH2, fb: 'Activities' }
        ];
        exploreH2Load.forEach(({ hid, val, fb }) => {
          const h = document.getElementById(hid);
          if (h) h.value = val || '';
          const text = (val && String(val).trim() !== '') ? String(val) : fb;
          document.querySelectorAll(`[data-field="${hid}"]`).forEach((node) => {
            node.textContent = text;
          });
        });

        writeExploreSectionCardsToAdmin('communities', defaultExploreCommunityCardsFromApi(data));
        writeExploreSectionCardsToAdmin('culture', defaultExploreCultureCardsFromApi(data));
        writeExploreSectionCardsToAdmin('activities', defaultExploreActivitiesCardsFromApi(data));

        attractionGalleries.forEach((attractionName) => {
          const galleryField = document.getElementById(`about-${attractionName}-gallery`);
          if (!galleryField) return;
          let gallery = [];
          try {
            const k = 'about' + attractionName.charAt(0).toUpperCase() + attractionName.slice(1) + 'Gallery';
            const galleryData = data[k] || '[]';
            gallery = JSON.parse(galleryData);
          } catch (e) {
            console.error(`Failed to parse gallery for ${attractionName}:`, e);
          }
          galleryField.value = JSON.stringify(gallery);
          updateAboutAttractionGalleryPreview(attractionName, gallery);
        });

        const parksTitleField = document.getElementById('about-parks-title');
        const parksIntroField = document.getElementById('about-parks-intro');
        const parksListField = document.getElementById('about-parks-list');
        const parksIntroPreview = document.getElementById('preview-about-parks-intro');
        if (parksTitleField) parksTitleField.value = data.aboutParksTitle || '';
        if (parksIntroField) parksIntroField.value = data.aboutParksIntro || '';
        if (parksIntroPreview) {
          parksIntroPreview.textContent = data.aboutParksIntro
            || 'If you enjoy spending time in nature, there are many hiking trails and several provincial parks near Back to Base.';
        }

        const parkCards = defaultExploreParkCardsFromApi(data);
        if (parksListField) {
          parksListField.value = parkCards.map((c) => (c.title || '').trim()).filter(Boolean).join('\n');
        }
        writeExploreParkCardsToAdmin(parkCards);
        if (typeof loadExploreImagesData === 'function') {
          loadExploreImagesData();
        }

        console.log('Explore page content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load Explore page data:', error);
  }
}

// Load about data
async function loadAboutData() {
  console.log('Loading about page data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Hero section - update hidden fields and preview
        const heroTitleField = document.getElementById('about-hero-title');
        const heroSubtitleField = document.getElementById('about-hero-subtitle');
        const heroTitlePreview = document.getElementById('preview-about-hero-title');
        const heroSubtitlePreview = document.getElementById('preview-about-hero-subtitle');
        if (heroTitleField) heroTitleField.value = data.aboutHeroTitle || '';
        if (heroSubtitleField) heroSubtitleField.value = data.aboutHeroSubtitle || '';
        if (heroTitlePreview) heroTitlePreview.textContent = data.aboutHeroTitle || 'About Back to Base';
        if (heroSubtitlePreview) heroSubtitlePreview.textContent = data.aboutHeroSubtitle || 'A personal retreat in the heart of British Columbia';
        
        // Idea and Origins: single text block (optional [[READ_MORE]]); legacy paragraph_1–3 merged on load
        const ideaTitleField = document.getElementById('about-idea-title');
        const ideaIntroField = document.getElementById('about-idea-intro');
        const ideaSignatureField = document.getElementById('about-idea-signature');
        const ideaTitlePreview = document.getElementById('preview-about-idea-title');
        const ideaIntroPreview = document.getElementById('preview-about-idea-intro');
        const ideaSignaturePreview = document.getElementById('preview-about-idea-signature');
        const pMergedLegacy = [data.aboutIdeaParagraph1, data.aboutIdeaParagraph2, data.aboutIdeaParagraph3]
          .map((s) => (s || '').trim())
          .filter(Boolean)
          .join('\n\n');
        let ideaEditorText = (data.aboutIdeaIntro || '');
        if (pMergedLegacy && !/\[\[READ_MORE\]\]/u.test(ideaEditorText)) {
          ideaEditorText = (ideaEditorText.trim() + (ideaEditorText.trim() ? '\n\n' : '') + '[[READ_MORE]]\n\n' + pMergedLegacy).trim();
        }
        if (ideaTitleField) ideaTitleField.value = data.aboutIdeaTitle || '';
        if (ideaIntroField) ideaIntroField.value = ideaEditorText;
        if (ideaSignatureField) ideaSignatureField.value = data.aboutIdeaSignature || '';
        if (ideaTitlePreview) ideaTitlePreview.textContent = data.aboutIdeaTitle || 'Idea and Origins';
        if (ideaIntroPreview) {
          ideaIntroPreview.textContent = ideaEditorText
            || 'Hi! My name is Rob Vuik. I founded Back to Base after twenty years of working as a co-owner of a large hotel in Nelson. When I retired, I realized something simple: many people — just like me — need a quiet place where they can rest, recover, and feel better.';
        }
        if (ideaSignaturePreview) ideaSignaturePreview.textContent = data.aboutIdeaSignature || 'I look forward to welcoming you!';
        
        // How to Find Us section
        const locTitleField = document.getElementById('about-location-title');
        const locP1Field = document.getElementById('about-location-paragraph-1');
        const locP2Field = document.getElementById('about-location-paragraph-2');
        const locP3Field = document.getElementById('about-location-paragraph-3');
        const locP4Field = document.getElementById('about-location-paragraph-4');
        const locCoordsField = document.getElementById('about-location-coordinates');
        const locDeerField = document.getElementById('about-location-deer-warning');
        const locTitlePreview = document.getElementById('preview-about-location-title');
        const locP1Preview = document.getElementById('preview-about-location-p1');
        const locP2Preview = document.getElementById('preview-about-location-p2');
        const locP3Preview = document.getElementById('preview-about-location-p3');
        const locP4Preview = document.getElementById('preview-about-location-p4');
        const locCoordsPreview = document.getElementById('preview-about-location-coords');
        const locDeerPreview = document.getElementById('preview-about-location-deer');
        if (locTitleField) locTitleField.value = data.aboutLocationTitle || '';
        if (locP1Field) locP1Field.value = data.aboutLocationParagraph1 || '';
        if (locP2Field) locP2Field.value = data.aboutLocationParagraph2 || '';
        if (locP3Field) locP3Field.value = data.aboutLocationParagraph3 || '';
        if (locP4Field) locP4Field.value = data.aboutLocationParagraph4 || '';
        if (locCoordsField) locCoordsField.value = data.aboutLocationCoordinates || '';
        if (locDeerField) locDeerField.value = data.aboutLocationDeerWarning || '';
        if (locTitlePreview) locTitlePreview.textContent = data.aboutLocationTitle || 'How to Find Us';
        if (locP1Preview) locP1Preview.textContent = data.aboutLocationParagraph1 || 'Back to Base is located in the village of Procter, 35 km from Nelson, B.C.';
        if (locP2Preview) locP2Preview.textContent = data.aboutLocationParagraph2 || 'You\'ll need to take the 24/7 Harrop–Procter ferry,';
        if (locP3Preview) locP3Preview.textContent = data.aboutLocationParagraph3 || 'then continue straight for another 6 minutes until you see the Back to Base sign on the right side of the road.';
        if (locP4Preview) locP4Preview.textContent = data.aboutLocationParagraph4 || 'From there, it\'s just a 3-minute drive up the mountain road — and you\'re here!';
        if (locCoordsPreview) locCoordsPreview.textContent = data.aboutLocationCoordinates || 'Coordinates: 49.6125, -116.9579';
        if (locDeerPreview) locDeerPreview.textContent = data.aboutLocationDeerWarning || '🦌 Be careful — we have a lot of deer in the area!';

        // Contact us block: section heading (h2) + text beside the form
        const cft = document.getElementById('about-contact-form-title');
        const cftP = document.getElementById('preview-about-contact-form-title');
        const tCont = (data.aboutContactFormTitle && String(data.aboutContactFormTitle).trim()) || 'Contact us';
        if (cft) cft.value = tCont;
        if (cftP) cftP.textContent = tCont;
        const cfd = document.getElementById('about-contact-form-description');
        const cfdP = document.getElementById('preview-about-contact-form-description');
        let dDesc = (data.aboutContactFormDescription && String(data.aboutContactFormDescription).trim()) || '';
        if (!dDesc) {
          const l = (data.aboutContactFormLead && String(data.aboutContactFormLead).trim()) || '';
          const e = (data.aboutContactFormEmphasis && String(data.aboutContactFormEmphasis).trim()) || '';
          dDesc = [l, e].filter(Boolean).join('\n\n');
        }
        if (!dDesc) {
          dDesc = "At Back to Base, you can find exactly the kind of rest you need.\n\nWe'll be happy to help you plan your stay and answer any questions!";
        }
        if (cfd) cfd.value = dDesc;
        if (cfdP) cfdP.textContent = dDesc;

        console.log('About page content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load about page data:', error);
  }
}

// Load about images data
async function loadAboutImagesData() {
  console.log('Loading about images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const heroImageUrl = result.data.aboutHeroImageUrl || '';
        const founderImageUrl = result.data.aboutFounderImageUrl || '';
        
        // Update preview images directly
        const heroImg = document.getElementById('preview-about-hero-img');
        const founderImg = document.getElementById('preview-about-founder-img');
        
        if (heroImg && heroImageUrl) {
          heroImg.src = heroImageUrl + '?v=' + Date.now();
          heroImg.style.display = 'block';
          const placeholder = heroImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        if (founderImg && founderImageUrl) {
          founderImg.src = founderImageUrl + '?v=' + Date.now();
          founderImg.style.display = 'block';
          const placeholder = founderImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }
        
        const stored = localStorage.getItem('btb_about_images') || '{}';
        const storedJson = JSON.parse(stored);
        const aboutImagesData = {
          ...storedJson,
          hero: heroImageUrl || storedJson.hero || '',
          founder: founderImageUrl || storedJson.founder || ''
        };
        localStorage.setItem('btb_about_images', JSON.stringify(aboutImagesData));
        console.log('About images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load about images data:', error);
  }
}

// Load Explore page images (hero + Procter main image)
async function loadExploreImagesData() {
  console.log('Loading Explore images data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');

    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });

    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const exploreHeroUrl = result.data.exploreHeroImageUrl || '';

        const heroImg = document.getElementById('preview-explore-hero-img');

        if (heroImg && exploreHeroUrl) {
          heroImg.src = exploreHeroUrl + '?v=' + Date.now();
          heroImg.style.display = 'block';
          const placeholder = heroImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
        }

        function applyExploreSectionHeroes(data) {
          const sections = [
            { key: 'communities', cards: defaultExploreCommunityCardsFromApi(data) },
            { key: 'culture', cards: defaultExploreCultureCardsFromApi(data) },
            { key: 'activities', cards: defaultExploreActivitiesCardsFromApi(data) }
          ];
          sections.forEach(({ key, cards }) => {
            const slug = EXPLORE_SECTION_CARD_META[key].uploadSlug;
            cards.forEach((c, idx) => {
              const n = idx + 1;
              const url = (c.hero_image_url && String(c.hero_image_url).trim()) ? String(c.hero_image_url).trim() : '';
              const img = document.getElementById(`preview-explore-${slug}-card-${n}-hero-img`);
              const hid = document.getElementById(`explore-${slug}-card-${n}-hero-url`);
              if (hid) hid.value = url;
              if (img && url) {
                img.src = url + '?v=' + Date.now();
                img.style.display = 'block';
                const placeholder = img.nextElementSibling;
                if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
              } else if (img && !url) {
                img.removeAttribute('src');
                img.style.display = 'none';
                const placeholder = img.nextElementSibling;
                if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = '';
              }
            });
          });
        }
        applyExploreSectionHeroes(result.data);

        const parkCardsForImg = defaultExploreParkCardsFromApi(result.data);
        for (let n = 1; n <= parkCardsForImg.length; n++) {
          const c = parkCardsForImg[n - 1] || {};
          const url = (c.hero_image_url && String(c.hero_image_url).trim()) ? String(c.hero_image_url).trim() : '';
          const img = document.getElementById(`preview-about-park-card-${n}-hero-img`);
          const hid = document.getElementById(`about-park-card-${n}-hero-url`);
          if (hid) hid.value = url;
          if (img && url) {
            img.src = url + '?v=' + Date.now();
            img.style.display = 'block';
            const placeholder = img.nextElementSibling;
            if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
          } else if (img && !url) {
            img.removeAttribute('src');
            img.style.display = 'none';
            const placeholder = img.nextElementSibling;
            if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = '';
          }
        }

        const stored = localStorage.getItem('btb_explore_images') || '{}';
        const storedJson = JSON.parse(stored);
        const accUrl = result.data.exploreAccommodationImageUrl || '';
        const miniH = result.data.miniHotelImageUrl || '';
        const accImg = document.getElementById('preview-explore-accommodation-img');
        const accShow = (accUrl && String(accUrl).trim() !== '' ? accUrl : miniH) || '';
        if (accImg && accShow) {
          accImg.src = accShow + '?v=' + Date.now();
          accImg.style.display = 'block';
          const ph = accImg.nextElementSibling;
          if (ph && ph.tagName === 'SPAN') ph.style.display = 'none';
        } else if (accImg) {
          accImg.removeAttribute('src');
          accImg.style.display = 'none';
          const ph = accImg.nextElementSibling;
          if (ph && ph.tagName === 'SPAN') ph.style.display = '';
        }

        const exploreImagesData = {
          ...storedJson,
          hero: exploreHeroUrl || storedJson.hero || '',
          accommodation: accUrl || storedJson.accommodation || ''
        };
        localStorage.setItem('btb_explore_images', JSON.stringify(exploreImagesData));
        console.log('Explore images data saved to localStorage');
      }
    }
  } catch (error) {
    console.log('Failed to load Explore images data:', error);
  }
}

// Initialize about image upload
function initAboutImageUpload() {
  const uploadConfigs = [
    { inputId: 'about-hero-upload', previewImgId: 'preview-about-hero-img', imageType: 'about-hero' },
    { inputId: 'about-founder-upload', previewImgId: 'preview-about-founder-img', imageType: 'about-founder' }
  ];

  uploadConfigs.forEach(config => {
    const fileInput = document.getElementById(config.inputId);
    const previewImg = document.getElementById(config.previewImgId);

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          // Update preview image immediately
          if (previewImg) {
            const reader = new FileReader();
            reader.onload = (event) => {
              previewImg.src = event.target.result;
              previewImg.style.display = 'block';
              const placeholder = previewImg.nextElementSibling;
              if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
          }
          
          // Use universal uploadImage function
          await uploadImage(file, config.imageType, null, null, {
            localStorageKey: 'btb_about_images',
            fieldNameMapper: (type) => {
              // Convert 'about-hero' to 'hero', 'about-founder' to 'founder', etc.
              return type.replace('about-', '');
            },
            reloadFunction: loadAboutImagesData,
            imageNameMapper: (type) => {
              const name = type.replace('about-', '');
              return name.charAt(0).toUpperCase() + name.slice(1);
            },
            onSuccess: (filepath) => {
              console.log('About image upload success, filepath:', filepath, 'imageType:', config.imageType);
              
              // Save to localStorage for immediate site update
              const stored = localStorage.getItem('btb_about_images') || '{}';
              const storedJson = JSON.parse(stored);
              const imageKey = config.imageType.replace('about-', '');
              storedJson[imageKey] = filepath;
              localStorage.setItem('btb_about_images', JSON.stringify(storedJson));
              console.log('Saved to btb_about_images:', imageKey, '=', filepath);
              
              // Also save to btb_content for site display
              let contentData = {};
              const contentStored = localStorage.getItem('btb_content');
              if (contentStored) {
                try {
                  contentData = JSON.parse(contentStored);
                } catch (e) {
                  console.error('Failed to parse btb_content:', e);
                }
              }
              const fieldName = 'about' + (imageKey.charAt(0).toUpperCase() + imageKey.slice(1)) + 'ImageUrl';
              contentData[fieldName] = filepath;
              localStorage.setItem('btb_content', JSON.stringify(contentData));
              console.log('Saved to btb_content:', fieldName, '=', filepath);
              
              // Save to server via save_content (upload_image.php already saves, but this ensures consistency)
              const contentFormData = new FormData();
              contentFormData.append('action', 'save_content');
              // Map imageKey to correct database field name
              const fieldMap = {
                'hero': 'about_hero_image_url',
                'founder': 'about_founder_image_url'
              };
              const dbFieldName = fieldMap[imageKey] || ('about_' + imageKey.replace('-', '_') + '_image_url');
              contentFormData.append(dbFieldName, filepath);
              console.log('Saving to server with field name:', dbFieldName, '=', filepath);
              
              fetch('api.php', {
                method: 'POST',
                body: contentFormData
              }).then(response => response.json()).then(result => {
                console.log('Save content response:', result);
                // Trigger auto-save status update
                if (typeof scheduleAboutAutoSave === 'function') {
                  scheduleAboutAutoSave();
                }
              }).catch(error => {
                console.error('Error saving about image to content:', error);
              });
            }
          });
        }
      });
    }
  });
}

// Initialize Explore page image uploads (hero only; section + park card heroes use delegation)
function initExploreImageUpload() {
  const uploadConfigs = [
    {
      inputId: 'explore-hero-upload',
      previewImgId: 'preview-explore-hero-img',
      imageType: 'explore-hero',
      storageKey: 'hero',
      contentDataKey: 'exploreHeroImageUrl',
      formField: 'explore_hero_image_url',
      parkSlot: null
    },
    {
      inputId: 'explore-accommodation-image-upload',
      previewImgId: 'preview-explore-accommodation-img',
      imageType: 'explore-accommodation',
      storageKey: 'accommodation',
      contentDataKey: 'exploreAccommodationImageUrl',
      formField: 'explore_accommodation_image_url',
      parkSlot: null
    }
  ];

  uploadConfigs.forEach((config) => {
    const fileInput = document.getElementById(config.inputId);
    const previewImg = document.getElementById(config.previewImgId);

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          if (previewImg) {
            const reader = new FileReader();
            reader.onload = (event) => {
              previewImg.src = event.target.result;
              previewImg.style.display = 'block';
              const placeholder = previewImg.nextElementSibling;
              if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
          }

          await uploadImage(file, config.imageType, null, null, {
            localStorageKey: 'btb_explore_images',
            fieldNameMapper: (type) => {
              if (type === 'explore-hero') return 'hero';
              if (type === 'explore-accommodation') return 'accommodation';
              const m = String(type).match(/^about-park-card-(\d+)-hero$/);
              if (m) return `parkCard${m[1]}Hero`;
              return type.replace('about-', '');
            },
            reloadFunction: loadExploreImagesData,
            imageNameMapper: (type) => type,
            onSuccess: (filepath) => {
              if (config.parkSlot != null) {
                const n = config.parkSlot;
                const hid = document.getElementById(`about-park-card-${n}-hero-url`);
                if (hid) hid.value = filepath;
                const cards = readExploreParkCardsFromAdmin();
                if (cards[n - 1]) cards[n - 1].hero_image_url = filepath;
                writeExploreParkCardsToAdmin(cards);
                persistExploreParkCardsToServer()
                  .then(() => {
                    if (typeof loadExploreImagesData === 'function') loadExploreImagesData();
                  })
                  .catch((err) => console.error('persist park cards hero', err));
                return;
              }

              const imageKey = config.storageKey;
              const stored = localStorage.getItem('btb_explore_images') || '{}';
              const storedJson = JSON.parse(stored);
              storedJson[imageKey] = filepath;
              localStorage.setItem('btb_explore_images', JSON.stringify(storedJson));

              let contentData = {};
              const contentStored = localStorage.getItem('btb_content');
              if (contentStored) {
                try {
                  contentData = JSON.parse(contentStored);
                } catch (err) {
                  console.error('Failed to parse btb_content:', err);
                }
              }
              contentData[config.contentDataKey] = filepath;
              localStorage.setItem('btb_content', JSON.stringify(contentData));

              const contentFormData = new FormData();
              contentFormData.append('action', 'save_content');
              contentFormData.append(config.formField, filepath);

              fetch('api.php', {
                method: 'POST',
                body: contentFormData
              }).then((response) => response.json()).then((result) => {
                console.log('Explore image save via save_content:', result);
                if (typeof scheduleExploreAutoSave === 'function') {
                  scheduleExploreAutoSave();
                }
              }).catch((err) => {
                console.error('Error saving explore image to content:', err);
              });
            }
          });
        }
      });
    }
  });
  bindExploreParkCardHeroUploadDelegation();
  initExploreSectionCardHeroUploadDelegation();
}

let exploreParkHeroDelegationBound = false;
function bindExploreParkCardHeroUploadDelegation() {
  const section = document.getElementById('explore-section');
  if (!section || exploreParkHeroDelegationBound) return;
  exploreParkHeroDelegationBound = true;
  section.addEventListener('change', async (e) => {
    const t = e.target;
    if (!t || !t.id || t.type !== 'file') return;
    const m = t.id.match(/^about-park-card-(\d+)-hero-upload$/);
    if (!m || !t.files || !t.files[0]) return;
    const slot = parseInt(m[1], 10);
    const file = t.files[0];
    const previewImg = document.getElementById(`preview-about-park-card-${slot}-hero-img`);
    if (previewImg) {
      const reader = new FileReader();
      reader.onload = (event) => {
        previewImg.src = event.target.result;
        previewImg.style.display = 'block';
        const placeholder = previewImg.nextElementSibling;
        if (placeholder && placeholder.tagName === 'SPAN') placeholder.style.display = 'none';
      };
      reader.readAsDataURL(file);
    }
    await uploadImage(file, `about-park-card-${slot}-hero`, null, null, {
      localStorageKey: 'btb_explore_images',
      fieldNameMapper: (type) => {
        if (type === 'explore-hero') return 'hero';
        if (type === 'explore-accommodation') return 'accommodation';
        const mm = String(type).match(/^about-park-card-(\d+)-hero$/);
        if (mm) return `parkCard${mm[1]}Hero`;
        return type.replace('about-', '');
      },
      reloadFunction: loadExploreImagesData,
      imageNameMapper: (type) => type,
      onSuccess: (filepath) => {
        const hid = document.getElementById(`about-park-card-${slot}-hero-url`);
        if (hid) hid.value = filepath;
        const cards = readExploreParkCardsFromAdmin();
        if (cards[slot - 1]) cards[slot - 1].hero_image_url = filepath;
        writeExploreParkCardsToAdmin(cards);
        persistExploreParkCardsToServer()
          .then(() => {
            if (typeof loadExploreImagesData === 'function') loadExploreImagesData();
          })
          .catch((err) => console.error('persist park cards hero', err));
      }
    });
    t.value = '';
  });
}

// Gallery management functions for attractions

// Update gallery preview for an attraction
function updateAboutAttractionGalleryPreview(attractionName, gallery) {
  const galleryPreview = document.getElementById(`about-${attractionName}-gallery-preview`);
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 80px; height: 80px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 2px; left: 2px; padding: 2px 6px; font-size: 0.7rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceAboutAttractionGalleryImage(attractionName, index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; padding: 0; font-size: 1rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteAboutAttractionGalleryImage(attractionName, index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if less than 10 photos
  if (gallery.length < 10) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 80px; height: 80px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 1.5rem;">+</span>';
    addItem.onclick = () => document.getElementById(`about-${attractionName}-gallery-upload`).click();
    galleryPreview.appendChild(addItem);
  }
}

// Replace gallery image
window.replaceAboutAttractionGalleryImage = function(attractionName, index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadAboutAttractionGalleryImage(attractionName, file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteAboutAttractionGalleryImage = function(attractionName, index) {
  const galleryField = document.getElementById(`about-${attractionName}-gallery`);
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateAboutAttractionGalleryPreview(attractionName, gallery);
  
  if (typeof scheduleExploreAutoSave === 'function') {
    if (typeof exploreHasUnsavedChanges !== 'undefined') {
      exploreHasUnsavedChanges = true;
    }
    scheduleExploreAutoSave();
  }
};

// Upload gallery image
async function uploadAboutAttractionGalleryImage(attractionName, file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', `about-${attractionName}-gallery`);
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      // Extract imageUrl from response
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log(`Gallery image upload result for ${attractionName}:`, result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById(`about-${attractionName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < 10) {
            gallery.push(imageUrl);
          } else {
            alert('Maximum 10 photos allowed in gallery');
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateAboutAttractionGalleryPreview(attractionName, gallery);
        
        console.log(`Gallery updated for ${attractionName}:`, gallery);
        console.log(`Gallery field value:`, galleryField.value);
        
        // Immediately save gallery to server
        const galleryFormData = new FormData();
        galleryFormData.append('action', 'save_content');
        galleryFormData.append(`about_${attractionName}_gallery`, galleryField.value);
        
        fetch('api.php', {
          method: 'POST',
          body: galleryFormData
        }).then(response => response.json()).then(result => {
          console.log(`Gallery saved to server for ${attractionName}:`, result);
          if (result.success) {
            console.log(`✓ Gallery for ${attractionName} successfully saved to database`);
          } else {
            console.error(`✗ Failed to save gallery for ${attractionName}:`, result.error);
          }
        }).catch(error => {
          console.error(`Error saving gallery for ${attractionName}:`, error);
        });
        
        // Also trigger auto-save for other fields (Explore page content)
        if (typeof scheduleExploreAutoSave === 'function') {
          if (typeof exploreHasUnsavedChanges !== 'undefined') {
            exploreHasUnsavedChanges = true;
          }
          scheduleExploreAutoSave();
        }
      }
    }
  } catch (error) {
    console.error(`Error uploading gallery image for ${attractionName}:`, error);
  }
}

// Initialize attraction gallery uploads
function initAboutAttractionGalleries() {
  attractionGalleries.forEach(attractionName => {
    // Add photo button
    const addBtn = document.getElementById(`about-${attractionName}-add-gallery-photo`);
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        document.getElementById(`about-${attractionName}-gallery-upload`).click();
      });
    }
    
    // Gallery upload input
    const galleryInput = document.getElementById(`about-${attractionName}-gallery-upload`);
    if (galleryInput) {
      galleryInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        
        const galleryField = document.getElementById(`about-${attractionName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        const remainingSlots = 10 - gallery.length;
        if (files.length > remainingSlots) {
          alert(`You can only add ${remainingSlots} more photo(s). Maximum 10 photos allowed.`);
          files.splice(remainingSlots);
        }
        
        for (const file of files) {
          await uploadAboutAttractionGalleryImage(attractionName, file);
        }
        
        // Reset input
        galleryInput.value = '';
      });
    }
  });
}

// ==========================================
// FLOOR PLAN GALLERY MANAGEMENT
// ==========================================

// Floor plan galleries
const floorplanGalleries = ['basement', 'ground', 'loft'];

// Update gallery preview for a floor plan floor
function updateFloorplanGalleryPreview(floorName, gallery) {
  const galleryPreview = document.getElementById(`floorplan-${floorName}-gallery-preview`);
  if (!galleryPreview) return;
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 80px; height: 80px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 2px; left: 2px; padding: 2px 6px; font-size: 0.7rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceFloorplanGalleryImage(floorName, index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; padding: 0; font-size: 1rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteFloorplanGalleryImage(floorName, index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if less than 10 photos
  if (gallery.length < 10) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 80px; height: 80px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 1.5rem;">+</span>';
    addItem.onclick = () => document.getElementById(`floorplan-${floorName}-gallery-upload`).click();
    galleryPreview.appendChild(addItem);
  }
}

// Replace gallery image
window.replaceFloorplanGalleryImage = function(floorName, index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadFloorplanGalleryImage(floorName, file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteFloorplanGalleryImage = function(floorName, index) {
  const galleryField = document.getElementById(`${floorName}-gallery`);
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateFloorplanGalleryPreview(floorName, gallery);
  
  // Save to server
  saveFloorplanGalleries();
};

// Upload gallery image
async function uploadFloorplanGalleryImage(floorName, file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', `floorplan-${floorName}-gallery`);
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log(`Gallery image upload result for ${floorName}:`, result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById(`${floorName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < 10) {
            gallery.push(imageUrl);
          } else {
            alert('Maximum 10 photos allowed in gallery');
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateFloorplanGalleryPreview(floorName, gallery);
        
        console.log(`Gallery updated for ${floorName}:`, gallery);
        
        // Save to server
        saveFloorplanGalleries();
      }
    }
  } catch (error) {
    console.error(`Error uploading gallery image for ${floorName}:`, error);
  }
}

// Initialize floor plan gallery uploads
function initFloorplanGalleries() {
  // Prevent double initialization
  if (window.floorplanGalleriesInitialized) {
    console.log('Floor plan galleries already initialized, skipping...');
    return;
  }
  window.floorplanGalleriesInitialized = true;
  
  floorplanGalleries.forEach(floorName => {
    // Add photo button - remove existing listeners first
    const addBtn = document.getElementById(`floorplan-${floorName}-add-gallery-photo`);
    if (addBtn) {
      // Clone button to remove all event listeners
      const newAddBtn = addBtn.cloneNode(true);
      addBtn.parentNode.replaceChild(newAddBtn, addBtn);
      
      newAddBtn.addEventListener('click', () => {
        document.getElementById(`floorplan-${floorName}-gallery-upload`).click();
      });
    }
    
    // Gallery upload input - remove existing listeners first
    const galleryInput = document.getElementById(`floorplan-${floorName}-gallery-upload`);
    if (galleryInput) {
      // Clone input to remove all event listeners
      const newGalleryInput = galleryInput.cloneNode(true);
      galleryInput.parentNode.replaceChild(newGalleryInput, galleryInput);
      
      newGalleryInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        
        const galleryField = document.getElementById(`${floorName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        const remainingSlots = 10 - gallery.length;
        if (files.length > remainingSlots) {
          alert(`You can only add ${remainingSlots} more photo(s). Maximum 10 photos allowed.`);
          files.splice(remainingSlots);
        }
        
        for (const file of files) {
          await uploadFloorplanGalleryImage(floorName, file);
        }
        
        // Reset input
        e.target.value = '';
      });
    }
  });
}

// Save floor plan galleries to server
function saveFloorplanGalleries() {
  const formData = new FormData();
  formData.append('action', 'save_floorplan');
  
  // Get all floor plan data - use same logic as saveFloorplanContent()
  const floorplanTitle = document.getElementById('floorplan-title')?.value || '';
  const floorplanSubtitle = document.getElementById('floorplan-subtitle')?.value || '';
  const basementSubtitle = document.getElementById('basement-subtitle')?.value || '';
  const basementDescription = document.getElementById('basement-description')?.value || '';
  const groundSubtitle = document.getElementById('ground-subtitle')?.value || '';
  const groundDescription = document.getElementById('ground-description')?.value || '';
  const loftSubtitle = document.getElementById('loft-subtitle')?.value || '';
  const loftDescription = document.getElementById('loft-description')?.value || '';
  
  // Get image URLs from path elements or localStorage (same as saveFloorplanContent)
  const stored = localStorage.getItem('btb_floorplan_settings');
  const storedJson = stored ? JSON.parse(stored) : {};
  const basementPathEl = document.getElementById('basement-image-path');
  const groundPathEl = document.getElementById('ground-image-path');
  const loftPathEl = document.getElementById('loft-image-path');
  const basementImageUrl = (basementPathEl && basementPathEl.textContent) || storedJson.basement_image_url || '';
  const groundQueenImage = (groundPathEl && groundPathEl.textContent) || storedJson.ground_image_url || storedJson.ground_queen_image || '';
  const loftImageUrl = (loftPathEl && loftPathEl.textContent) || storedJson.loft_image_url || '';
  
  formData.append('floorplanTitle', floorplanTitle);
  formData.append('floorplanSubtitle', floorplanSubtitle);
  formData.append('basementSubtitle', basementSubtitle);
  formData.append('basementDescription', basementDescription);
  formData.append('basementImageUrl', basementImageUrl);
  formData.append('groundSubtitle', groundSubtitle);
  formData.append('groundDescription', groundDescription);
  formData.append('groundQueenImage', groundQueenImage);
  formData.append('groundTwinImage', '');
  formData.append('loftSubtitle', loftSubtitle);
  formData.append('loftDescription', loftDescription);
  formData.append('loftImageUrl', loftImageUrl);
  
  // Add galleries
  formData.append('basementGallery', document.getElementById('basement-gallery')?.value || '[]');
  formData.append('groundGallery', document.getElementById('ground-gallery')?.value || '[]');
  formData.append('loftGallery', document.getElementById('loft-gallery')?.value || '[]');
  
  fetch('api.php', {
    method: 'POST',
    body: formData
  }).then(async response => {
    // Get response text first to check what we received
    const responseText = await response.text();
    console.log('saveFloorplanGalleries: Response status:', response.status);
    console.log('saveFloorplanGalleries: Response text:', responseText.substring(0, 500));
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      console.error('Server returned non-JSON response. Status:', response.status);
      console.error('Response text:', responseText);
      throw new Error('Server returned non-JSON response (status ' + response.status + '): ' + responseText.substring(0, 200));
    }
    
    // Try to parse JSON
    try {
      return JSON.parse(responseText);
    } catch (e) {
      console.error('Failed to parse JSON response:', e);
      console.error('Response text:', responseText);
      throw new Error('Invalid JSON response: ' + responseText.substring(0, 200));
    }
  }).then(result => {
    console.log('Floor plan galleries saved to server:', result);
    if (result.success) {
      console.log('✓ Floor plan galleries successfully saved to database');
    } else {
      console.error('✗ Failed to save floor plan galleries:', result.error);
      if (result.error_details) {
        console.error('Error details:', result.error_details);
      }
    }
  }).catch(error => {
    console.error('Error saving floor plan galleries:', error);
    alert('Error saving galleries: ' + error.message);
  });
}

// Retreat location gallery functions
function updateRetreatLocationGalleryPreview(locationName, gallery) {
  console.log(`updateRetreatLocationGalleryPreview called for ${locationName} with gallery:`, gallery);
  const galleryPreview = document.getElementById(`retreat-${locationName}-gallery-preview`);
  if (!galleryPreview) {
    console.error(`Gallery preview element not found for ${locationName}: retreat-${locationName}-gallery-preview`);
    return;
  }
  console.log(`Gallery preview element found for ${locationName}`);
  
  galleryPreview.innerHTML = '';
  
  gallery.forEach((imageUrl, index) => {
    const galleryItem = document.createElement('div');
    galleryItem.style.cssText = 'position: relative; width: 80px; height: 80px; border: 2px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #f3f4f6;';
    
    const img = document.createElement('img');
    img.src = imageUrl + '?v=' + Date.now();
    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
    
    const replaceBtn = document.createElement('button');
    replaceBtn.textContent = 'Replace';
    replaceBtn.className = 'admin-btn admin-btn-secondary';
    replaceBtn.style.cssText = 'position: absolute; top: 2px; left: 2px; padding: 2px 6px; font-size: 0.7rem; z-index: 10; background: rgba(59, 130, 246, 0.9); color: white; border: none; border-radius: 4px; cursor: pointer;';
    replaceBtn.onclick = (e) => {
      e.stopPropagation();
      replaceRetreatLocationGalleryImage(locationName, index);
    };
    
    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = '×';
    deleteBtn.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; padding: 0; font-size: 1rem; line-height: 1; z-index: 10; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteRetreatLocationGalleryImage(locationName, index);
    };
    
    galleryItem.appendChild(img);
    galleryItem.appendChild(replaceBtn);
    galleryItem.appendChild(deleteBtn);
    galleryPreview.appendChild(galleryItem);
  });
  
  // Show add button if less than 10 photos
  if (gallery.length < 10) {
    const addItem = document.createElement('div');
    addItem.style.cssText = 'width: 80px; height: 80px; border: 2px dashed #9ca3af; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #f9fafb;';
    addItem.innerHTML = '<span style="color: #9ca3af; font-size: 1.5rem;">+</span>';
    addItem.onclick = () => document.getElementById(`retreat-${locationName}-gallery-upload`).click();
    galleryPreview.appendChild(addItem);
  }
}

// Replace gallery image
window.replaceRetreatLocationGalleryImage = function(locationName, index) {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      await uploadRetreatLocationGalleryImage(locationName, file, index);
    }
  };
  input.click();
};

// Delete gallery image
window.deleteRetreatLocationGalleryImage = function(locationName, index) {
  const galleryField = document.getElementById(`retreat-${locationName}-gallery`);
  if (!galleryField) return;
  
  let gallery = [];
  try {
    gallery = JSON.parse(galleryField.value || '[]');
  } catch (e) {
    console.error('Failed to parse gallery:', e);
    return;
  }
  
  gallery.splice(index, 1);
  galleryField.value = JSON.stringify(gallery);
  updateRetreatLocationGalleryPreview(locationName, gallery);
  
  if (typeof scheduleRetreatAutoSave === 'function') {
    if (typeof retreatHasUnsavedChanges !== 'undefined') {
      retreatHasUnsavedChanges = true;
    }
    scheduleRetreatAutoSave();
  }
};

// Upload gallery image
async function uploadRetreatLocationGalleryImage(locationName, file, replaceIndex = null) {
  try {
    const formData = new FormData();
    formData.append('action', 'upload_image');
    formData.append('image_type', `retreat-${locationName}-gallery`);
    formData.append('image', file);
    
    const response = await fetch('upload_image.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      const payload = result && result.data ? result.data : result;
      const imageUrl = payload && payload.imageUrl ? payload.imageUrl : (payload && payload.filepath ? payload.filepath : (result.imageUrl || result.filepath || ''));
      console.log(`Gallery image upload result for ${locationName}:`, result);
      console.log('Extracted imageUrl:', imageUrl);
      if (result.success && imageUrl) {
        const galleryField = document.getElementById(`retreat-${locationName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        if (replaceIndex !== null && replaceIndex >= 0 && replaceIndex < gallery.length) {
          gallery[replaceIndex] = imageUrl;
        } else {
          if (gallery.length < 10) {
            gallery.push(imageUrl);
          } else {
            alert('Maximum 10 photos allowed in gallery');
            return;
          }
        }
        
        galleryField.value = JSON.stringify(gallery);
        updateRetreatLocationGalleryPreview(locationName, gallery);
        
        console.log(`Gallery updated for ${locationName}:`, gallery);
        console.log(`Gallery field value:`, galleryField.value);
        
        // Immediately save to server
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = true;
          console.log(`Set retreatHasUnsavedChanges = true for ${locationName} gallery`);
        }
        
        // Immediately save gallery to server
        setTimeout(async () => {
          console.log(`Triggering immediate save for ${locationName} gallery`);
          console.log(`Current gallery field value:`, galleryField.value);
          
          // Save gallery directly to API
          try {
            const galleryFormData = new FormData();
            galleryFormData.append('action', 'save_content');
            galleryFormData.append(`retreat_${locationName}_gallery`, galleryField.value);
            
            console.log(`Saving ${locationName} gallery to API:`, galleryField.value);
            
            const saveResponse = await fetch('api.php', {
              method: 'POST',
              body: galleryFormData
            });
            
            if (saveResponse.ok) {
              const saveResult = await saveResponse.json();
              console.log(`Save response for ${locationName} gallery:`, saveResult);
              if (saveResult.success) {
                console.log(`✓ Gallery for ${locationName} successfully saved to database`);
              } else {
                console.error(`✗ Failed to save ${locationName} gallery:`, saveResult.error);
              }
            } else {
              console.error(`✗ Failed to save ${locationName} gallery: HTTP ${saveResponse.status}`);
            }
          } catch (error) {
            console.error(`✗ Error saving ${locationName} gallery:`, error);
          }
          
          // Also trigger full save to ensure all data is synced
          if (typeof retreatHasUnsavedChanges !== 'undefined') {
            retreatHasUnsavedChanges = true;
          }
          if (typeof scheduleRetreatAutoSave === 'function') {
            scheduleRetreatAutoSave();
          }
        }, 500);
      }
    }
  } catch (error) {
    console.error('Error uploading gallery image:', error);
  }
}

// Initialize retreat location galleries
function initRetreatLocationGalleries() {
  const locationNames = ['forest', 'indoor', 'theatre'];
  
  locationNames.forEach(locationName => {
    // Add button
    const addBtn = document.getElementById(`retreat-${locationName}-add-gallery-photo`);
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        document.getElementById(`retreat-${locationName}-gallery-upload`).click();
      });
    }
    
    // Gallery upload input
    const galleryInput = document.getElementById(`retreat-${locationName}-gallery-upload`);
    if (galleryInput) {
      galleryInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        
        const galleryField = document.getElementById(`retreat-${locationName}-gallery`);
        if (!galleryField) return;
        
        let gallery = [];
        try {
          gallery = JSON.parse(galleryField.value || '[]');
        } catch (e) {
          console.error('Failed to parse gallery:', e);
        }
        
        const remainingSlots = 10 - gallery.length;
        if (files.length > remainingSlots) {
          alert(`You can only add ${remainingSlots} more photo(s). Maximum 10 photos allowed.`);
          files.splice(remainingSlots);
        }
        
        for (const file of files) {
          await uploadRetreatLocationGalleryImage(locationName, file);
        }
        
        // Reset input
        galleryInput.value = '';
      });
    }
  });
}

// About auto-save functionality
let aboutAutoSaveTimer = null;
let aboutHasUnsavedChanges = false;

function scheduleAboutAutoSave() {
  if (aboutAutoSaveTimer) {
    clearTimeout(aboutAutoSaveTimer);
  }
  
  aboutAutoSaveTimer = setTimeout(() => {
    if (aboutHasUnsavedChanges) {
      saveAboutContent();
      aboutHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateAboutSaveStatus('saving');
}

async function saveAboutContent() {
  updateAboutSaveStatus('saving');
  
  try {
    // Contenteditable previews sync to hidden fields on blur; auto-save may run first — force sync so DB gets latest text
    document.querySelectorAll('#about-section [data-field]').forEach((el) => {
      const fid = el.getAttribute('data-field');
      if (fid && typeof syncPreviewToForm === 'function') {
        syncPreviewToForm(el, fid);
      }
    });

    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('about_hero_title', document.getElementById('about-hero-title')?.value || '');
    formData.append('about_hero_subtitle', document.getElementById('about-hero-subtitle')?.value || '');
    formData.append('about_idea_title', document.getElementById('about-idea-title')?.value || '');
    formData.append('about_idea_intro', document.getElementById('about-idea-intro')?.value || '');
    formData.append('about_idea_paragraph_1', '');
    formData.append('about_idea_paragraph_2', '');
    formData.append('about_idea_paragraph_3', '');
    formData.append('about_idea_signature', document.getElementById('about-idea-signature')?.value || '');
    formData.append('about_location_title', document.getElementById('about-location-title')?.value || '');
    formData.append('about_location_paragraph_1', document.getElementById('about-location-paragraph-1')?.value || '');
    formData.append('about_location_paragraph_2', document.getElementById('about-location-paragraph-2')?.value || '');
    formData.append('about_location_paragraph_3', document.getElementById('about-location-paragraph-3')?.value || '');
    formData.append('about_location_paragraph_4', document.getElementById('about-location-paragraph-4')?.value || '');
    formData.append('about_location_coordinates', document.getElementById('about-location-coordinates')?.value || '');
    formData.append('about_location_deer_warning', document.getElementById('about-location-deer-warning')?.value || '');
    formData.append('about_contact_form_title', document.getElementById('about-contact-form-title')?.value || '');
    formData.append('about_contact_form_description', document.getElementById('about-contact-form-description')?.value || '');
    
    // Get image URLs from localStorage
    const imagesStored = localStorage.getItem('btb_about_images') || '{}';
    const imagesJson = JSON.parse(imagesStored);
    formData.append('about_hero_image_url', imagesJson.hero || '');
    formData.append('about_founder_image_url', imagesJson.founder || '');
    
    await postApiFormDataAndUpdateStatus('about', formData);
  } catch (error) {
    console.error('Error saving about content:', error);
    updateAboutSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateAboutSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('about', status, detail);
}

function initAboutAutoSave() {
  // syncPreviewToForm already handles triggering auto-save for about fields
  // This function is here for consistency with retreat/special pattern
  console.log('About auto-save initialized');
}

// Explore page auto-save
let exploreAutoSaveTimer = null;
let exploreHasUnsavedChanges = false;

function scheduleExploreAutoSave() {
  if (exploreAutoSaveTimer) {
    clearTimeout(exploreAutoSaveTimer);
  }

  exploreAutoSaveTimer = setTimeout(() => {
    if (exploreHasUnsavedChanges) {
      saveExploreContent();
      exploreHasUnsavedChanges = false;
    }
  }, 2000);

  updateExploreSaveStatus('saving');
}

async function saveExploreContent() {
  updateExploreSaveStatus('saving');

  try {
    const formData = new FormData();
    formData.append('action', 'save_content');

    formData.append('explore_hero_title', document.getElementById('explore-hero-title')?.value || '');
    formData.append('explore_hero_subtitle', document.getElementById('explore-hero-subtitle')?.value || '');

    formData.append('explore_communities_h2', document.getElementById('explore-communities-h2')?.value || '');
    formData.append('explore_culture_h2', document.getElementById('explore-culture-h2')?.value || '');
    formData.append('explore_parks_h2', document.getElementById('explore-parks-h2')?.value || '');
    formData.append('explore_activities_h2', document.getElementById('explore-activities-h2')?.value || '');
    formData.append('explore_communities_cards', JSON.stringify(readExploreSectionCardsFromAdmin('communities')));
    formData.append('explore_culture_cards', JSON.stringify(readExploreSectionCardsFromAdmin('culture')));
    formData.append('explore_activities_cards', JSON.stringify(readExploreSectionCardsFromAdmin('activities')));

    const commIntroVal = document.getElementById('explore-communities-intro')?.value || '';
    formData.append('explore_communities_intro', commIntroVal);
    formData.append('explore_culture_intro', document.getElementById('explore-culture-intro')?.value || '');
    formData.append('explore_activities_intro', document.getElementById('explore-activities-intro')?.value || '');
    formData.append('about_attractions_title', '');
    formData.append('about_attractions_lead', commIntroVal);
    formData.append('about_procter_title', document.getElementById('about-procter-title')?.value || '');
    formData.append('about_procter_distance', document.getElementById('about-procter-distance')?.value || '');
    formData.append('about_procter_description', document.getElementById('about-procter-description')?.value || '');
    formData.append('about_halcyon_title', document.getElementById('about-halcyon-title')?.value || '');
    formData.append('about_halcyon_distance', document.getElementById('about-halcyon-distance')?.value || '');
    formData.append('about_halcyon_description', document.getElementById('about-halcyon-description')?.value || '');
    formData.append('about_whitewater_title', document.getElementById('about-whitewater-title')?.value || '');
    formData.append('about_whitewater_distance', document.getElementById('about-whitewater-distance')?.value || '');
    formData.append('about_whitewater_description', document.getElementById('about-whitewater-description')?.value || '');
    formData.append('about_nelson_title', document.getElementById('about-nelson-title')?.value || '');
    formData.append('about_nelson_distance', document.getElementById('about-nelson-distance')?.value || '');
    formData.append('about_nelson_description', document.getElementById('about-nelson-description')?.value || '');
    formData.append('about_kaslo_title', document.getElementById('about-kaslo-title')?.value || '');
    formData.append('about_kaslo_distance', document.getElementById('about-kaslo-distance')?.value || '');
    formData.append('about_kaslo_description', document.getElementById('about-kaslo-description')?.value || '');
    formData.append('about_crawford_title', document.getElementById('about-crawford-title')?.value || '');
    formData.append('about_crawford_distance', document.getElementById('about-crawford-distance')?.value || '');
    formData.append('about_crawford_description', document.getElementById('about-crawford-description')?.value || '');
    formData.append('about_museum_title', document.getElementById('about-museum-title')?.value || '');
    formData.append('about_museum_distance', document.getElementById('about-museum-distance')?.value || '');
    formData.append('about_museum_description', document.getElementById('about-museum-description')?.value || '');
    formData.append('about_parks_title', document.getElementById('about-parks-title')?.value || '');
    formData.append('about_parks_intro', document.getElementById('about-parks-intro')?.value || '');
    const parkCardsPayload = readExploreParkCardsFromAdmin();
    formData.append('about_parks_cards', JSON.stringify(parkCardsPayload));
    formData.append(
      'about_parks_list',
      parkCardsPayload.map((c) => (c.title || '').trim()).filter(Boolean).join('\n')
    );

    const exStore = localStorage.getItem('btb_explore_images') || '{}';
    const exJson = JSON.parse(exStore);
    formData.append('explore_hero_image_url', exJson.hero || '');
    formData.append('explore_accommodation_image_url', exJson.accommodation || '');
    formData.append('explore_accommodation_title', document.getElementById('explore-accommodation-title')?.value || '');
    formData.append('explore_accommodation_description', document.getElementById('explore-accommodation-description')?.value || '');
    formData.append('about_procter_image_url', exJson.procter || '');
    formData.append('about_nelson_image_url', exJson.nelson || '');
    formData.append('about_kaslo_image_url', exJson.kaslo || '');
    formData.append('about_crawford_image_url', exJson.crawford || '');
    formData.append('about_museum_image_url', exJson.museum || '');

    attractionGalleries.forEach((attractionName) => {
      const galleryField = document.getElementById(`about-${attractionName}-gallery`);
      if (galleryField) {
        formData.append(`about_${attractionName}_gallery`, galleryField.value || '[]');
      }
    });

    const { ok } = await postApiFormDataAndUpdateStatus('explore', formData);
    if (ok) {
      setTimeout(() => {
        if (typeof loadExploreData === 'function') {
          loadExploreData();
        }
      }, 400);
    }
  } catch (error) {
    console.error('Error saving explore content:', error);
    updateExploreSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateExploreSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('explore', status, detail);
}

function initExploreAutoSave() {
  console.log('Explore auto-save initialized');
}

// Load contact data (phone, email, address) from get_content. Same pattern as other CMS blocks:
// contenteditable previews + hidden fields stay in sync; data comes from API, not long-lived HTML defaults.
async function loadContactData() {
  if (contactDataLoadInFlight) {
    return contactDataLoadInFlight;
  }
  const work = (async function contactDataLoad() {
    console.log('Loading contact data...');
    const phoneField = document.getElementById('contact-phone');
    const emailField = document.getElementById('contact-email');
    const addressField = document.getElementById('contact-address');
    const phonePreview = document.getElementById('preview-contact-phone');
    const emailPreview = document.getElementById('preview-contact-email');
    const addressPreview = document.getElementById('preview-contact-address');

    // Remove stale placeholder copy from admin.html before the network returns (avoids default-text flash)
    if (phonePreview) phonePreview.textContent = '';
    if (emailPreview) emailPreview.textContent = '';
    if (addressPreview) addressPreview.textContent = '';

    function applyContactValues(phoneVal, emailVal, addressVal) {
      if (phoneField) phoneField.value = phoneVal;
      if (emailField) emailField.value = emailVal;
      if (addressField) addressField.value = addressVal;
      if (phonePreview) phonePreview.textContent = phoneVal;
      if (emailPreview) emailPreview.textContent = emailVal;
      if (addressPreview) addressPreview.textContent = addressVal;
    }

    try {
      const formData = new FormData();
      formData.append('action', 'get_content');
      const response = await fetch('api.php', { method: 'POST', body: formData });
      if (!response.ok) {
        throw new Error('get_content failed: ' + response.status);
      }
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        const phoneVal = data.contactPhone != null ? String(data.contactPhone) : '';
        const emailVal = data.contactEmail != null ? String(data.contactEmail) : '';
        const addressVal = data.contactAddress != null ? String(data.contactAddress) : '';
        applyContactValues(phoneVal, emailVal, addressVal);
        console.log('Contact data loaded successfully');
      } else {
        applyContactValues(DEFAULT_CONTACT_PHONE, DEFAULT_CONTACT_EMAIL, DEFAULT_CONTACT_ADDRESS);
      }
    } catch (error) {
      console.log('Failed to load contact data:', error);
      applyContactValues(DEFAULT_CONTACT_PHONE, DEFAULT_CONTACT_EMAIL, DEFAULT_CONTACT_ADDRESS);
    }
  })();
  contactDataLoadInFlight = work;
  try {
    await work;
  } finally {
    contactDataLoadInFlight = null;
  }
}

// Contact auto-save (contactAutoSaveTimer + contactHasUnsavedChanges defined at top of file)

window.scheduleContactAutoSave = function() {
  if (contactAutoSaveTimer) {
    clearTimeout(contactAutoSaveTimer);
  }
  
  contactAutoSaveTimer = setTimeout(() => {
    if (contactHasUnsavedChanges) {
      saveContactContent();
      contactHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateContactSaveStatus('saving');
}

async function saveContactContent() {
  updateContactSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('contact_phone', document.getElementById('contact-phone')?.value || '');
    formData.append('contact_email', document.getElementById('contact-email')?.value || '');
    formData.append('contact_address', document.getElementById('contact-address')?.value || '');
    
    await postApiFormDataAndUpdateStatus('contact', formData);
  } catch (error) {
    console.error('Error saving contact content:', error);
    updateContactSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateContactSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('contact', status, detail);
}

function initContactAutoSave() {
  console.log('Contact auto-save initialized');
}

// Room Second auto-save functionality
let roomSecondAutoSaveTimer = null;
let roomSecondHasUnsavedChanges = false;

window.scheduleRoomSecondAutoSave = function() {
  if (roomSecondAutoSaveTimer) {
    clearTimeout(roomSecondAutoSaveTimer);
  }
  
  roomSecondAutoSaveTimer = setTimeout(() => {
    if (roomSecondHasUnsavedChanges) {
      saveRoomSecondContent();
      roomSecondHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateRoomSecondSaveStatus('saving');
}

async function saveRoomSecondContent() {
  updateRoomSecondSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('room_second_title', document.getElementById('room-second-title')?.value || '');
    formData.append('room_second_subtitle', document.getElementById('room-second-subtitle')?.value || '');
    formData.append('room_second_description', document.getElementById('room-second-description')?.value || '');
    formData.append('room_second_price_prefix', document.getElementById('room-second-price-prefix')?.value || '');
    formData.append('room_second_price_amount', document.getElementById('room-second-price-amount')?.value || '');
    formData.append('room_second_price_suffix', document.getElementById('room-second-price-suffix')?.value || '');
    formData.append('room_second_capacity', document.getElementById('room-second-capacity')?.value || '');
    formData.append('room_second_note', document.getElementById('room-second-note')?.value || '');
    formData.append('room_second_gallery', document.getElementById('room-second-gallery')?.value || '[]');
    formData.append(
      'room_second_gallery_section_title',
      document.getElementById('room-second-gallery-section-title')?.value || ''
    );
    formData.append('room_second_common_gallery', document.getElementById('room-second-common-gallery')?.value || '[]');
    formData.append(
      'room_second_common_gallery_section_title',
      document.getElementById('room-second-common-gallery-section-title')?.value || ''
    );
    
    const { ok } = await postApiFormDataAndUpdateStatus('room-second', formData);
    if (ok) {
      void refreshHomepageRoomCardPricePreviewsFromApi();
    }
  } catch (error) {
    console.error('Error saving room second content:', error);
    updateRoomSecondSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateRoomSecondSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('room-second', status, detail);
}

function initRoomSecondAutoSave() {
  console.log('Room Second auto-save initialized');
}

// Room Ground Twin auto-save functionality
let roomGroundTwinAutoSaveTimer = null;
let roomGroundTwinHasUnsavedChanges = false;

window.scheduleRoomGroundTwinAutoSave = function() {
  if (roomGroundTwinAutoSaveTimer) {
    clearTimeout(roomGroundTwinAutoSaveTimer);
  }
  
  roomGroundTwinAutoSaveTimer = setTimeout(() => {
    if (roomGroundTwinHasUnsavedChanges) {
      saveRoomGroundTwinContent();
      roomGroundTwinHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateRoomGroundTwinSaveStatus('saving');
}

async function saveRoomGroundTwinContent() {
  updateRoomGroundTwinSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('room_ground_twin_title', document.getElementById('room-ground-twin-title')?.value || '');
    formData.append('room_ground_twin_subtitle', document.getElementById('room-ground-twin-subtitle')?.value || '');
    formData.append('room_ground_twin_description', document.getElementById('room-ground-twin-description')?.value || '');
    formData.append('room_ground_twin_price_prefix', document.getElementById('room-ground-twin-price-prefix')?.value || '');
    formData.append('room_ground_twin_price_amount', document.getElementById('room-ground-twin-price-amount')?.value || '');
    formData.append('room_ground_twin_price_suffix', document.getElementById('room-ground-twin-price-suffix')?.value || '');
    formData.append('room_ground_twin_capacity', document.getElementById('room-ground-twin-capacity')?.value || '');
    formData.append('room_ground_twin_note', document.getElementById('room-ground-twin-note')?.value || '');
    formData.append('room_ground_twin_gallery', document.getElementById('room-ground-twin-gallery')?.value || '[]');
    formData.append(
      'room_ground_twin_gallery_section_title',
      document.getElementById('room-ground-twin-gallery-section-title')?.value || ''
    );
    formData.append('room_ground_twin_common_gallery', document.getElementById('room-ground-twin-common-gallery')?.value || '[]');
    formData.append(
      'room_ground_twin_common_gallery_section_title',
      document.getElementById('room-ground-twin-common-gallery-section-title')?.value || ''
    );
    
    const { ok } = await postApiFormDataAndUpdateStatus('room-ground-twin', formData);
    if (ok) {
      void refreshHomepageRoomCardPricePreviewsFromApi();
    }
  } catch (error) {
    console.error('Error saving room ground twin content:', error);
    updateRoomGroundTwinSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateRoomGroundTwinSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('room-ground-twin', status, detail);
}

function initRoomGroundTwinAutoSave() {
  console.log('Room Ground Twin auto-save initialized');
}

// Room Ground Queen auto-save functionality
let roomGroundQueenAutoSaveTimer = null;
let roomGroundQueenHasUnsavedChanges = false;

window.scheduleRoomGroundQueenAutoSave = function() {
  if (roomGroundQueenAutoSaveTimer) {
    clearTimeout(roomGroundQueenAutoSaveTimer);
  }
  
  roomGroundQueenAutoSaveTimer = setTimeout(() => {
    if (roomGroundQueenHasUnsavedChanges) {
      saveRoomGroundQueenContent();
      roomGroundQueenHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateRoomGroundQueenSaveStatus('saving');
}

async function saveRoomGroundQueenContent() {
  updateRoomGroundQueenSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('room_ground_queen_title', document.getElementById('room-ground-queen-title')?.value || '');
    formData.append('room_ground_queen_subtitle', document.getElementById('room-ground-queen-subtitle')?.value || '');
    formData.append('room_ground_queen_description', document.getElementById('room-ground-queen-description')?.value || '');
    formData.append('room_ground_queen_price_prefix', document.getElementById('room-ground-queen-price-prefix')?.value || '');
    formData.append('room_ground_queen_price_amount', document.getElementById('room-ground-queen-price-amount')?.value || '');
    formData.append('room_ground_queen_price_suffix', document.getElementById('room-ground-queen-price-suffix')?.value || '');
    formData.append('room_ground_queen_capacity', document.getElementById('room-ground-queen-capacity')?.value || '');
    formData.append('room_ground_queen_note', document.getElementById('room-ground-queen-note')?.value || '');
    formData.append('room_ground_queen_gallery', document.getElementById('room-ground-queen-gallery')?.value || '[]');
    formData.append(
      'room_ground_queen_gallery_section_title',
      document.getElementById('room-ground-queen-gallery-section-title')?.value || ''
    );
    formData.append('room_ground_queen_common_gallery', document.getElementById('room-ground-queen-common-gallery')?.value || '[]');
    formData.append(
      'room_ground_queen_common_gallery_section_title',
      document.getElementById('room-ground-queen-common-gallery-section-title')?.value || ''
    );
    
    const { ok } = await postApiFormDataAndUpdateStatus('room-ground-queen', formData);
    if (ok) {
      void refreshHomepageRoomCardPricePreviewsFromApi();
    }
  } catch (error) {
    console.error('Error saving room ground queen content:', error);
    updateRoomGroundQueenSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateRoomGroundQueenSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('room-ground-queen', status, detail);
}

function initRoomGroundQueenAutoSave() {
  console.log('Room Ground Queen auto-save initialized');
}

// Room Basement auto-save functionality
let roomBasementAutoSaveTimer = null;
let roomBasementHasUnsavedChanges = false;

window.scheduleRoomBasementAutoSave = function() {
  if (roomBasementAutoSaveTimer) {
    clearTimeout(roomBasementAutoSaveTimer);
  }
  
  roomBasementAutoSaveTimer = setTimeout(() => {
    if (roomBasementHasUnsavedChanges) {
      saveRoomBasementContent();
      roomBasementHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateRoomBasementSaveStatus('saving');
}

async function saveRoomBasementContent() {
  updateRoomBasementSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('room_basement_title', document.getElementById('room-basement-title')?.value || '');
    formData.append('room_basement_subtitle', document.getElementById('room-basement-subtitle')?.value || '');
    formData.append('room_basement_description', document.getElementById('room-basement-description')?.value || '');
    formData.append('room_basement_price_prefix', document.getElementById('room-basement-price-prefix')?.value || '');
    formData.append('room_basement_price_amount', document.getElementById('room-basement-price-amount')?.value || '');
    formData.append('room_basement_price_suffix', document.getElementById('room-basement-price-suffix')?.value || '');
    formData.append('room_basement_capacity', document.getElementById('room-basement-capacity')?.value || '');
    formData.append('room_basement_note', document.getElementById('room-basement-note')?.value || '');
    formData.append('room_basement_gallery', document.getElementById('room-basement-gallery')?.value || '[]');
    formData.append(
      'room_basement_gallery_section_title',
      document.getElementById('room-basement-gallery-section-title')?.value || ''
    );
    formData.append('room_basement_common_gallery', document.getElementById('room-basement-common-gallery')?.value || '[]');
    formData.append(
      'room_basement_common_gallery_section_title',
      document.getElementById('room-basement-common-gallery-section-title')?.value || ''
    );
    
    const { ok } = await postApiFormDataAndUpdateStatus('room-basement', formData);
    if (ok) {
      void refreshHomepageRoomCardPricePreviewsFromApi();
    }
  } catch (error) {
    console.error('Error saving room basement content:', error);
    updateRoomBasementSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateRoomBasementSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('room-basement', status, detail);
}

function initRoomBasementAutoSave() {
  console.log('Room Basement auto-save initialized');
}

// Wellness Experiences auto-save functionality
let wellnessAutoSaveTimer = null;
let wellnessHasUnsavedChanges = false;

window.scheduleWellnessAutoSave = function() {
  if (wellnessAutoSaveTimer) {
    clearTimeout(wellnessAutoSaveTimer);
  }
  
  wellnessAutoSaveTimer = setTimeout(() => {
    if (wellnessHasUnsavedChanges) {
      saveWellnessContent();
      wellnessHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateWellnessSaveStatus('saving');
}

async function saveWellnessContent() {
  updateWellnessSaveStatus('saving');
  
  try {
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('wellness_title', document.getElementById('wellness-title')?.value || '');
    formData.append('wellness_description', document.getElementById('wellness-description')?.value || '');
    formData.append('wellness_massage_title', document.getElementById('wellness-massage-title')?.value || '');
    formData.append('wellness_massage_description', document.getElementById('wellness-massage-description')?.value || '');
    formData.append('wellness_yoga_title', '');
    formData.append('wellness_yoga_description', '');
    
    await postApiFormDataAndUpdateStatus('wellness', formData);
  } catch (error) {
    console.error('Error saving wellness content:', error);
    updateWellnessSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateWellnessSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('wellness', status, detail);
}

function initWellnessAutoSave() {
  console.log('Wellness Experiences auto-save initialized');
}

// Massage auto-save functionality
let massageAutoSaveTimer = null;
let massageHasUnsavedChanges = false;

window.scheduleMassageAutoSave = function() {
  console.log('scheduleMassageAutoSave: Called, massageHasUnsavedChanges =', massageHasUnsavedChanges);
  if (massageAutoSaveTimer) {
    clearTimeout(massageAutoSaveTimer);
  }
  
  massageAutoSaveTimer = setTimeout(() => {
    console.log('scheduleMassageAutoSave: Timer fired, massageHasUnsavedChanges =', massageHasUnsavedChanges);
    if (massageHasUnsavedChanges) {
      saveMassageContent();
      massageHasUnsavedChanges = false;
    } else {
      console.log('scheduleMassageAutoSave: No unsaved changes, skipping save');
    }
  }, 2000); // 2 second delay
  
  updateMassageSaveStatus('saving');
}

async function saveMassageContent() {
  updateMassageSaveStatus('saving');
  console.log('saveMassageContent: Starting save...');
  
  try {
    // Sync all preview fields to form before saving to ensure latest changes are included
    const miniHotelTitlePreview = document.getElementById('preview-mini-hotel-title');
    const miniHotelDescPreview = document.getElementById('preview-mini-hotel-desc');
    if (miniHotelTitlePreview) {
      syncPreviewToForm(miniHotelTitlePreview, 'mini-hotel-title');
    }
    if (miniHotelDescPreview) {
      syncPreviewToForm(miniHotelDescPreview, 'mini-hotel-description');
    }
    const bookingTitlePrev = document.getElementById('preview-massage-booking-title');
    const bookingIntroPrev = document.getElementById('preview-massage-booking-intro');
    const bookingEmptyPrev = document.getElementById('preview-massage-booking-empty-hint');
    if (bookingTitlePrev) syncPreviewToForm(bookingTitlePrev, 'massage-booking-title');
    if (bookingIntroPrev) syncPreviewToForm(bookingIntroPrev, 'massage-booking-intro');
    if (bookingEmptyPrev) syncPreviewToForm(bookingEmptyPrev, 'massage-booking-empty-hint');
    
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    formData.append('massage_hero_title', document.getElementById('massage-hero-title')?.value || '');
    formData.append('massage_intro', document.getElementById('massage-intro')?.value || '');
    formData.append('massage_relaxing_title', document.getElementById('massage-relaxing-title')?.value || '');
    formData.append('massage_relaxing_description', document.getElementById('massage-relaxing-description')?.value || '');
    formData.append('massage_deep_tissue_title', document.getElementById('massage-deep-tissue-title')?.value || '');
    formData.append('massage_deep_tissue_description', document.getElementById('massage-deep-tissue-description')?.value || '');
    formData.append('massage_reiki_title', document.getElementById('massage-reiki-title')?.value || '');
    formData.append('massage_reiki_description', document.getElementById('massage-reiki-description')?.value || '');
    formData.append('massage_sauna_title', document.getElementById('massage-sauna-title')?.value || '');
    formData.append('massage_sauna_description', document.getElementById('massage-sauna-description')?.value || '');
    formData.append('massage_booking_title', document.getElementById('massage-booking-title')?.value || '');
    formData.append('massage_booking_intro', document.getElementById('massage-booking-intro')?.value || '');
    formData.append('massage_pricing_relaxing', collectMassagePricingJsonForKey('relaxing'));
    formData.append('massage_pricing_deep_tissue', collectMassagePricingJsonForKey('deep'));
    formData.append('massage_pricing_reiki', collectMassagePricingJsonForKey('reiki'));
    formData.append('massage_pricing_sauna', collectMassagePricingJsonForKey('sauna'));
    
    const miniHotelTitleField = document.getElementById('mini-hotel-title');
    const miniHotelDescField = document.getElementById('mini-hotel-description');
    const miniHotelTitle = miniHotelTitleField?.value || '';
    const miniHotelDescription = miniHotelDescField?.value || '';
    console.log('saveMassageContent: Mini-hotel fields:', {
      titleFieldExists: !!miniHotelTitleField,
      titleFieldValue: miniHotelTitle,
      descFieldExists: !!miniHotelDescField,
      descFieldValue: miniHotelDescription.substring(0, 80)
    });
    formData.append('mini_hotel_title', miniHotelTitle);
    formData.append('mini_hotel_description', miniHotelDescription);
    console.log('saveMassageContent: Sending data:', {
      mini_hotel_title: miniHotelTitle.substring(0, 50),
      mini_hotel_description: miniHotelDescription.substring(0, 50)
    });
    
    await postApiFormDataAndUpdateStatus('massage', formData);
  } catch (error) {
    console.error('Error saving massage content:', error);
    updateMassageSaveStatus('error', (error && error.message) || 'Save failed');
  }
}

function updateMassageSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('massage', status, detail);
}

function initMassageAutoSave() {
  console.log('Massage auto-save initialized');
}

// ==========================================
// HOMEPAGE ROOMS CARDS MANAGEMENT
// ==========================================

const BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML = {
  basement: '<strong>Price:</strong> 140 CAD / night',
  groundQueen: '<strong>Price:</strong> 130 CAD / night',
  groundTwin: '<strong>Price:</strong> 125 CAD / night',
  second: '<strong>Price:</strong> 210 CAD / night (entire floor)'
};

/** Same HTML line as index.php / room pages; server returns trusted HTML from api.php get_content. */
function btbSetHomepageRoomCardPricePreview(element, serverHtml, fallbackHtml) {
  if (!element) {
    return;
  }
  const raw = serverHtml == null ? '' : String(serverHtml);
  element.innerHTML = raw.trim() !== '' ? raw : fallbackHtml;
}

/** After saving a room nightly price, refresh read-only homepage card price previews (same as get_content / site). */
async function refreshHomepageRoomCardPricePreviewsFromApi() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    const response = await fetch('api.php', { method: 'POST', body: formData });
    if (!response.ok) {
      return;
    }
    const result = await response.json();
    if (!result.success || !result.data) {
      return;
    }
    const d = result.data;
    const fb = BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML;
    const rows = [
      ['preview-room-basement-card-price', d.roomBasementCardPrice, fb.basement],
      ['preview-room-ground-queen-card-price', d.roomGroundQueenCardPrice, fb.groundQueen],
      ['preview-room-ground-twin-card-price', d.roomGroundTwinCardPrice, fb.groundTwin],
      ['preview-room-second-card-price', d.roomSecondCardPrice, fb.second]
    ];
    rows.forEach(([id, val, fallbackHtml]) => {
      const el = document.getElementById(id);
      btbSetHomepageRoomCardPricePreview(el, val, fallbackHtml);
    });
  } catch (e) {
    console.warn('refreshHomepageRoomCardPricePreviewsFromApi:', e);
  }
}

// Load homepage rooms cards data
async function loadHomepageRoomsData() {
  console.log('Loading homepage rooms cards data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Try to get from localStorage first (for immediate display)
        const stored = localStorage.getItem('btb_content') || '{}';
        const storedJson = JSON.parse(stored);
        
        // Rooms Section Title and Subtitle
        const roomsTitleField = document.getElementById('rooms-title');
        const roomsSubtitleField = document.getElementById('rooms-subtitle');
        const roomsTitlePreview = document.getElementById('preview-rooms-title');
        const roomsSubtitlePreview = document.getElementById('preview-rooms-subtitle');
        
        // Use stored value if available, otherwise use data from server
        const title = storedJson.roomsTitle || data.roomsTitle || 'Choose your room';
        const subtitle = storedJson.roomsSubtitle !== undefined ? storedJson.roomsSubtitle : (data.roomsSubtitle || '');
        
        console.log('Loading rooms title/subtitle:', {
          fromServer: { roomsTitle: data.roomsTitle, roomsSubtitle: data.roomsSubtitle },
          fromLocalStorage: { roomsTitle: storedJson.roomsTitle, roomsSubtitle: storedJson.roomsSubtitle },
          final: { title, subtitle }
        });
        
        if (roomsTitleField) {
          roomsTitleField.value = title;
          console.log('Set rooms-title field value:', title);
          if (roomsTitlePreview) {
            roomsTitlePreview.textContent = title;
            console.log('Set preview-rooms-title textContent:', title);
          }
        }
        if (roomsSubtitleField) {
          roomsSubtitleField.value = subtitle;
          console.log('Set rooms-subtitle field value:', subtitle);
          if (roomsSubtitlePreview) {
            roomsSubtitlePreview.textContent = subtitle;
            console.log('Set preview-rooms-subtitle textContent:', subtitle);
          }
        }
        
        // Update localStorage with server data
        storedJson.roomsTitle = title;
        storedJson.roomsSubtitle = subtitle;
        localStorage.setItem('btb_content', JSON.stringify(storedJson));
        
        // Basement card
        const basementTitleField = document.getElementById('room-basement-card-title');
        const basementDescField = document.getElementById('room-basement-card-description');
        const basementTitlePreview = document.getElementById('preview-room-basement-card-title');
        const basementDescPreview = document.getElementById('preview-room-basement-card-description');
        const basementPricePreview = document.getElementById('preview-room-basement-card-price');
        
        console.log('Loading basement card data:', {
          roomBasementCardTitle: data.roomBasementCardTitle,
          roomBasementCardDescription: data.roomBasementCardDescription,
          roomBasementCardPrice: data.roomBasementCardPrice
        });
        
        if (basementTitleField) {
          const title = data.roomBasementCardTitle || 'Loki Suite';
          basementTitleField.value = title;
          console.log('Set room-basement-card-title:', title);
          if (basementTitlePreview) {
            basementTitlePreview.textContent = title.replace(/<br\s*\/?>/gi, '\n');
            basementTitlePreview.innerHTML = title.replace(/<br\s*\/?>/gi, '<br>');
          }
        }
        if (basementDescField) {
          const desc = data.roomBasementCardDescription || '';
          basementDescField.value = desc;
          console.log('Set room-basement-card-description:', desc);
          if (basementDescPreview) {
            basementDescPreview.textContent = desc.replace(/\n/g, '\n');
            basementDescPreview.textContent = desc;
          }
        }
        if (basementPricePreview) {
          btbSetHomepageRoomCardPricePreview(
            basementPricePreview,
            data.roomBasementCardPrice,
            BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML.basement
          );
          console.log('Set preview room card price (from CMS nightly):', data.roomBasementCardPrice);
        }
        
        // Update basement card image
        const basementImg = document.getElementById('preview-room-basement-card-img');
        const basementImageUrl = data.roomBasementCardImageUrl || '';
        if (basementImg && basementImageUrl) {
          basementImg.src = basementImageUrl + '?v=' + Date.now();
          basementImg.style.display = 'block';
          const placeholder = basementImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        // Ground Queen card
        const groundQueenTitleField = document.getElementById('room-ground-queen-card-title');
        const groundQueenDescField = document.getElementById('room-ground-queen-card-description');
        const groundQueenTitlePreview = document.getElementById('preview-room-ground-queen-card-title');
        const groundQueenDescPreview = document.getElementById('preview-room-ground-queen-card-description');
        const groundQueenPricePreview = document.getElementById('preview-room-ground-queen-card-price');
        
        if (groundQueenTitleField) {
          const title = data.roomGroundQueenCardTitle || 'The Nouk';
          groundQueenTitleField.value = title;
          if (groundQueenTitlePreview) {
            groundQueenTitlePreview.textContent = title.replace(/<br\s*\/?>/gi, '\n');
            groundQueenTitlePreview.innerHTML = title.replace(/<br\s*\/?>/gi, '<br>');
          }
        }
        if (groundQueenDescField) {
          const desc = data.roomGroundQueenCardDescription || '';
          groundQueenDescField.value = desc;
          if (groundQueenDescPreview) {
            groundQueenDescPreview.textContent = desc.replace(/\n/g, '\n');
            groundQueenDescPreview.textContent = desc;
          }
        }
        if (groundQueenPricePreview) {
          btbSetHomepageRoomCardPricePreview(
            groundQueenPricePreview,
            data.roomGroundQueenCardPrice,
            BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML.groundQueen
          );
        }
        
        // Update ground queen card image
        const groundQueenImg = document.getElementById('preview-room-ground-queen-card-img');
        const groundQueenImageUrl = data.roomGroundQueenCardImageUrl || '';
        if (groundQueenImg && groundQueenImageUrl) {
          groundQueenImg.src = groundQueenImageUrl + '?v=' + Date.now();
          groundQueenImg.style.display = 'block';
          const placeholder = groundQueenImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        // Ground Twin card
        const groundTwinTitleField = document.getElementById('room-ground-twin-card-title');
        const groundTwinDescField = document.getElementById('room-ground-twin-card-description');
        const groundTwinTitlePreview = document.getElementById('preview-room-ground-twin-card-title');
        const groundTwinDescPreview = document.getElementById('preview-room-ground-twin-card-description');
        const groundTwinPricePreview = document.getElementById('preview-room-ground-twin-card-price');
        
        if (groundTwinTitleField) {
          const title = data.roomGroundTwinCardTitle || 'Vrienden';
          groundTwinTitleField.value = title;
          if (groundTwinTitlePreview) {
            groundTwinTitlePreview.textContent = title.replace(/<br\s*\/?>/gi, '\n');
            groundTwinTitlePreview.innerHTML = title.replace(/<br\s*\/?>/gi, '<br>');
          }
        }
        if (groundTwinDescField) {
          const desc = data.roomGroundTwinCardDescription || '';
          groundTwinDescField.value = desc;
          if (groundTwinDescPreview) {
            groundTwinDescPreview.textContent = desc.replace(/\n/g, '\n');
            groundTwinDescPreview.textContent = desc;
          }
        }
        if (groundTwinPricePreview) {
          btbSetHomepageRoomCardPricePreview(
            groundTwinPricePreview,
            data.roomGroundTwinCardPrice,
            BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML.groundTwin
          );
        }
        
        // Update ground twin card image
        const groundTwinImg = document.getElementById('preview-room-ground-twin-card-img');
        const groundTwinImageUrl = data.roomGroundTwinCardImageUrl || '';
        if (groundTwinImg && groundTwinImageUrl) {
          groundTwinImg.src = groundTwinImageUrl + '?v=' + Date.now();
          groundTwinImg.style.display = 'block';
          const placeholder = groundTwinImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        // Second card
        const secondTitleField = document.getElementById('room-second-card-title');
        const secondDescField = document.getElementById('room-second-card-description');
        const secondTitlePreview = document.getElementById('preview-room-second-card-title');
        const secondDescPreview = document.getElementById('preview-room-second-card-description');
        const secondPricePreview = document.getElementById('preview-room-second-card-price');
        
        if (secondTitleField) {
          const title = data.roomSecondCardTitle || 'Kelder';
          secondTitleField.value = title;
          if (secondTitlePreview) {
            secondTitlePreview.textContent = title.replace(/<br\s*\/?>/gi, '\n');
            secondTitlePreview.innerHTML = title.replace(/<br\s*\/?>/gi, '<br>');
          }
        }
        if (secondDescField) {
          const desc = data.roomSecondCardDescription || '';
          secondDescField.value = desc;
          if (secondDescPreview) {
            secondDescPreview.textContent = desc.replace(/\n/g, '\n');
            secondDescPreview.textContent = desc;
          }
        }
        if (secondPricePreview) {
          btbSetHomepageRoomCardPricePreview(
            secondPricePreview,
            data.roomSecondCardPrice,
            BTB_HOMEPAGE_ROOM_CARD_PRICE_FALLBACK_HTML.second
          );
        }
        
        // Update second card image
        const secondImg = document.getElementById('preview-room-second-card-img');
        const secondImageUrl = data.roomSecondCardImageUrl || '';
        if (secondImg && secondImageUrl) {
          secondImg.src = secondImageUrl + '?v=' + Date.now();
          secondImg.style.display = 'block';
          const placeholder = secondImg.nextElementSibling;
          if (placeholder && placeholder.tagName === 'SPAN') {
            placeholder.style.display = 'none';
          }
        }
        
        console.log('Homepage rooms cards content loaded successfully');
      }
    }
  } catch (error) {
    console.log('Failed to load homepage rooms cards data:', error);
  }
}

// Initialize homepage rooms image upload
function initHomepageRoomsImageUpload() {
  const uploadConfigs = [
    {
      inputId: 'room-basement-card-upload',
      imageType: 'room-basement-card'
    },
    {
      inputId: 'room-ground-queen-card-upload',
      imageType: 'room-ground-queen-card'
    },
    {
      inputId: 'room-ground-twin-card-upload',
      imageType: 'room-ground-twin-card'
    },
    {
      inputId: 'room-second-card-upload',
      imageType: 'room-second-card'
    }
  ];

  uploadConfigs.forEach(config => {
    const fileInput = document.getElementById(config.inputId);

    if (fileInput) {
      // Remove existing event listeners by cloning the element
      const newFileInput = fileInput.cloneNode(true);
      fileInput.parentNode.replaceChild(newFileInput, fileInput);
      
      // Add change event listener
      newFileInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (file) {
          console.log(`Uploading ${config.imageType} image:`, file.name);
          await uploadImage(file, config.imageType, null, null, {
            localStorageKey: 'btb_homepage_rooms',
            fieldNameMapper: (type) => type.replace('room-', '').replace('-card', '') + 'CardImageUrl',
            reloadFunction: loadHomepageRoomsData,
            imageNameMapper: (type) => type.replace('room-', '').replace('-card', '').charAt(0).toUpperCase() + type.replace('room-', '').replace('-card', '').slice(1) + ' Card'
          });
          // Reset input to allow selecting the same file again
          e.target.value = '';
        }
      });
    } else {
      console.warn(`File input not found: ${config.inputId}`);
    }
  });
}

// ==========================================
// WELLNESS EXPERIENCES MANAGEMENT
// ==========================================

// Load wellness experiences data
async function loadWellnessExperiencesData() {
  console.log('Loading wellness experiences data...');
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Section header
        const titleField = document.getElementById('wellness-title');
        const descField = document.getElementById('wellness-description');
        const titlePreview = document.getElementById('preview-wellness-title');
        const descPreview = document.getElementById('preview-wellness-desc');
        if (titleField) titleField.value = data.wellnessTitle || '';
        if (descField) descField.value = data.wellnessDescription || '';
        if (titlePreview) titlePreview.textContent = data.wellnessTitle || 'Wellness Experiences';
        if (descPreview) {
          const previewText = data.wellnessDescription || 'Enhance your stay with optional massage: relaxing or deep tissue sessions with an experienced therapist — an easy way to make your time in the mountains feel even more restorative.';
          descPreview.textContent = previewText;
        }
        
        // Massage card
        const massageTitleField = document.getElementById('wellness-massage-title');
        const massageDescField = document.getElementById('wellness-massage-description');
        const massageTitlePreview = document.getElementById('preview-wellness-massage-title');
        const massageDescPreview = document.getElementById('preview-wellness-massage-desc');
        const massageImg = document.getElementById('preview-wellness-massage-img');
        const massageImageUrl = result.data.wellnessMassageImageUrl || '';
        if (massageTitleField) massageTitleField.value = data.wellnessMassageTitle || '';
        if (massageDescField) massageDescField.value = data.wellnessMassageDescription || '';
        if (massageTitlePreview) massageTitlePreview.textContent = data.wellnessMassageTitle || 'Wellness';
        if (massageDescPreview) {
          const massageText = data.wellnessMassageDescription || 'Our guesthouse has a massage room with an experienced therapist who will be happy to make your stay even more enjoyable. Whether you prefer a relaxing massage or a therapeutic deep tissue session — the choice is yours.';
          massageDescPreview.textContent = massageText;
        }
        if (massageImg && massageImageUrl) {
          massageImg.src = massageImageUrl + '?v=' + Date.now();
          massageImg.style.display = 'block';
          const span = massageImg.parentElement.querySelector('span');
          if (span) span.style.display = 'none';
        }
      }
    }
  } catch (error) {
    console.log('Failed to load wellness experiences data:', error);
  }
}

// Initialize wellness experiences image upload
function initWellnessExperiencesImageUpload() {
  // Massage image upload
  const massageInput = document.getElementById('wellness-massage-upload');
  if (massageInput) {
    massageInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (file) {
        const massageImg = document.getElementById('preview-wellness-massage-img');
        await uploadImage(file, 'wellness-massage', null, null, {
          localStorageKey: 'btb_wellness_experiences',
          fieldNameMapper: () => 'massage.imageUrl',
          reloadFunction: loadWellnessExperiencesData,
          imageNameMapper: () => 'Wellness Massage',
          onSuccess: (imageUrl) => {
            if (massageImg) {
              massageImg.src = imageUrl + '?v=' + Date.now();
              massageImg.style.display = 'block';
              const span = massageImg.parentElement.querySelector('span');
              if (span) span.style.display = 'none';
            }
            setTimeout(() => {
              loadWellnessExperiencesData();
            }, 1000);
          }
        });
      }
    });
  }
}

// Initialize save handlers for room pages
function initRoomPageSaveHandlers() {
  // Basement
  const saveBasementBtn = document.getElementById('save-room-basement');
  if (saveBasementBtn) {
    saveBasementBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_basement') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('basement-banner-path');
      
      const data = {
        title: document.getElementById('basement-page-title').value,
        subtitle: document.getElementById('basement-page-subtitle').value,
        description: document.getElementById('basement-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_basement', JSON.stringify(data));
      showStatus('Basement page saved successfully!');
    });
  }

  // Ground Queen
  const saveGroundQueenBtn = document.getElementById('save-room-ground-queen');
  if (saveGroundQueenBtn) {
    saveGroundQueenBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_ground_queen') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('ground-queen-banner-path');
      
      const data = {
        title: document.getElementById('ground-queen-page-title').value,
        subtitle: document.getElementById('ground-queen-page-subtitle').value,
        description: document.getElementById('ground-queen-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_ground_queen', JSON.stringify(data));
      showStatus('Ground Queen page saved successfully!');
    });
  }

  // Ground Twin
  const saveGroundTwinBtn = document.getElementById('save-room-ground-twin');
  if (saveGroundTwinBtn) {
    saveGroundTwinBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_ground_twin') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('ground-twin-banner-path');
      
      const data = {
        title: document.getElementById('ground-twin-page-title').value,
        subtitle: document.getElementById('ground-twin-page-subtitle').value,
        description: document.getElementById('ground-twin-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_ground_twin', JSON.stringify(data));
      showStatus('Ground Twin page saved successfully!');
    });
  }

  // Second Floor
  const saveSecondBtn = document.getElementById('save-room-second');
  if (saveSecondBtn) {
    saveSecondBtn.addEventListener('click', async () => {
      const stored = localStorage.getItem('btb_room_second') || '{}';
      const storedJson = JSON.parse(stored);
      const bannerPathEl = document.getElementById('second-banner-path');
      
      const data = {
        title: document.getElementById('second-page-title').value,
        subtitle: document.getElementById('second-page-subtitle').value,
        description: document.getElementById('second-page-description').value,
        bannerImageUrl: (bannerPathEl && bannerPathEl.textContent) || storedJson.bannerImageUrl || ''
      };
      
      localStorage.setItem('btb_room_second', JSON.stringify(data));
      showStatus('Second Floor page saved successfully!');
    });
  }
}

// Initialize save handlers for page content
function initPageContentSaveHandlers() {
  // Retreat and Workshop
  const saveRetreatBtn = document.getElementById('save-retreat-workshop');
  if (saveRetreatBtn) {
    saveRetreatBtn.addEventListener('click', async () => {
      console.log('Saving retreat and workshop content...');
      
      // Sync every contenteditable preview to hidden fields (same as auto-save)
      document.querySelectorAll('#retreat-workshop-section .editable-preview').forEach((previewEl) => {
        const fieldId = previewEl.getAttribute('data-field');
        if (fieldId) {
          syncPreviewToForm(previewEl, fieldId);
        }
      });
      
      const formData = new FormData();
      formData.append('action', 'save_content');
      
      // Hero section
      const heroTitle = document.getElementById('retreat-hero-title')?.value || '';
      const heroSubtitle = document.getElementById('retreat-hero-subtitle')?.value || '';
      console.log('Hero title:', heroTitle);
      console.log('Hero subtitle:', heroSubtitle);
      formData.append('retreat_hero_title', heroTitle);
      formData.append('retreat_hero_subtitle', heroSubtitle);
      
      // Locations section
      formData.append('retreat_locations_title', document.getElementById('retreat-locations-title')?.value || '');
      
      // Forest Platforms card
      formData.append('retreat_forest_title', document.getElementById('retreat-forest-title')?.value || '');
      formData.append('retreat_forest_description', document.getElementById('retreat-forest-description')?.value || '');
      formData.append('retreat_forest_list_label', '');
      formData.append('retreat_forest_list_items', '');
      
      // Indoor Space card
      formData.append('retreat_indoor_title', document.getElementById('retreat-indoor-title')?.value || '');
      formData.append('retreat_indoor_description', document.getElementById('retreat-indoor-description')?.value || '');
      formData.append('retreat_indoor_additional', '');
      
      // Home Theatre card
      formData.append('retreat_theatre_title', document.getElementById('retreat-theatre-title')?.value || '');
      formData.append('retreat_theatre_description', document.getElementById('retreat-theatre-description')?.value || '');
      
      // Contact Form section
      formData.append('retreat_contact_title', document.getElementById('retreat-contact-title')?.value || '');
      formData.append('retreat_contact_text', document.getElementById('retreat-contact-text')?.value || '');
      
      // Removed from site: keep DB fields cleared on save
      formData.append('retreat_organizer_title', '');
      formData.append('retreat_workshops_title', '');
      formData.append('retreat_workshops_intro', '');
      formData.append('retreat_workshops_list', '');
      formData.append('retreat_workshops_conclusion', '');
      
      // Collaboration section
      formData.append('retreat_collaboration_title', document.getElementById('retreat-collaboration-title')?.value || '');
      formData.append('retreat_collaboration_intro', document.getElementById('retreat-collaboration-intro')?.value || '');
      formData.append('retreat_collaboration_list', '');
      formData.append('retreat_collaboration_conclusion', '');
      
      try {
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        if (response.ok) {
          const result = await response.json();
          console.log('Save response:', result);
          if (result.success) {
            showStatus('Retreat and workshop content saved successfully!');
            
            // Update auto-save status
            if (typeof retreatHasUnsavedChanges !== 'undefined') {
              retreatHasUnsavedChanges = false;
            }
            if (typeof updateRetreatSaveStatus === 'function') {
              updateRetreatSaveStatus('Saved', '✓');
              setTimeout(() => {
                if (typeof retreatHasUnsavedChanges !== 'undefined' && !retreatHasUnsavedChanges) {
                  updateRetreatSaveStatus('', '');
                }
              }, 3000);
            }
            
            // Save to localStorage for immediate site update
            const retreatContent = {
              retreatHeroTitle: document.getElementById('retreat-hero-title')?.value || '',
              retreatHeroSubtitle: document.getElementById('retreat-hero-subtitle')?.value || '',
              retreatLocationsTitle: document.getElementById('retreat-locations-title')?.value || '',
              retreatForestTitle: document.getElementById('retreat-forest-title')?.value || '',
              retreatForestDescription: document.getElementById('retreat-forest-description')?.value || '',
              retreatIndoorTitle: document.getElementById('retreat-indoor-title')?.value || '',
              retreatIndoorDescription: document.getElementById('retreat-indoor-description')?.value || '',
              retreatTheatreTitle: document.getElementById('retreat-theatre-title')?.value || '',
              retreatTheatreDescription: document.getElementById('retreat-theatre-description')?.value || '',
              retreatContactTitle: document.getElementById('retreat-contact-title')?.value || '',
              retreatContactText: document.getElementById('retreat-contact-text')?.value || '',
              retreatCollaborationTitle: document.getElementById('retreat-collaboration-title')?.value || '',
              retreatCollaborationIntro: document.getElementById('retreat-collaboration-intro')?.value || ''
            };
            localStorage.setItem('btb_retreat_workshop_content', JSON.stringify(retreatContent));
            console.log('Retreat content saved to localStorage');
            
            // Keep preview in sync with hidden fields (do not refetch — avoids newline / list loss)
            updateRetreatPreview(buildRetreatDataFromFormForPreview());
          } else {
            showStatus('Failed to save: ' + (result.error || 'Unknown error'), 'error');
            if (typeof updateRetreatSaveStatus === 'function') {
              updateRetreatSaveStatus('Save error', '❌');
            }
            if (typeof retreatHasUnsavedChanges !== 'undefined') {
              retreatHasUnsavedChanges = true;
            }
          }
        } else {
          showStatus('Failed to save retreat content', 'error');
          if (typeof updateRetreatSaveStatus === 'function') {
            updateRetreatSaveStatus('Save error', '❌');
          }
          if (typeof retreatHasUnsavedChanges !== 'undefined') {
            retreatHasUnsavedChanges = true;
          }
        }
      } catch (error) {
        console.error('Error saving retreat content:', error);
        showStatus('Error saving retreat content: ' + error.message, 'error');
        if (typeof updateRetreatSaveStatus === 'function') {
          updateRetreatSaveStatus('Save error', '❌');
        }
        if (typeof retreatHasUnsavedChanges !== 'undefined') {
          retreatHasUnsavedChanges = true;
        }
      }
    });
  }

// Auto-save for Retreats and Workshops
let retreatAutoSaveTimer = null;
let retreatHasUnsavedChanges = false;
let retreatIsSaving = false;
let retreatSaveRetryCount = 0;
const RETREAT_AUTO_SAVE_DELAY = 2000; // 2 seconds
const RETREAT_MAX_RETRIES = 3;

// Make functions globally accessible
function scheduleRetreatAutoSave() {
  // Ensure flag is set before scheduling
  if (typeof retreatHasUnsavedChanges !== 'undefined') {
    retreatHasUnsavedChanges = true;
    console.log('scheduleRetreatAutoSave: Setting retreatHasUnsavedChanges = true');
  }
  
  // Clear existing timer
  if (retreatAutoSaveTimer) {
    clearTimeout(retreatAutoSaveTimer);
    console.log('scheduleRetreatAutoSave: Cleared existing timer');
  }
  
  // Update status to show pending save
  const updateStatus = window.updateRetreatSaveStatus || updateRetreatSaveStatus;
  if (typeof updateStatus === 'function') {
    updateStatus('Unsaved changes', '⏳');
  } else {
    console.warn('updateRetreatSaveStatus is not defined');
  }
  
  // Schedule auto-save
  console.log('Scheduling auto-save in', RETREAT_AUTO_SAVE_DELAY, 'ms, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  retreatAutoSaveTimer = setTimeout(() => {
    console.log('Auto-save timer fired, calling autoSaveRetreatContent...');
    console.log('At timer fire, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
    const autoSave = window.autoSaveRetreatContent || autoSaveRetreatContent;
    if (typeof autoSave === 'function') {
      autoSave();
    } else {
      console.warn('autoSaveRetreatContent is not defined');
    }
  }, RETREAT_AUTO_SAVE_DELAY);
}

// Make it globally accessible
window.scheduleRetreatAutoSave = scheduleRetreatAutoSave;

function initRetreatAutoSave() {
  if (window._retreatAutoSaveListenersAttached) {
    return;
  }
  window._retreatAutoSaveListenersAttached = true;
  // Track changes in preview fields (contenteditable)
  const previewFields = document.querySelectorAll('#retreat-workshop-section .editable-preview');
  previewFields.forEach(field => {
    field.addEventListener('input', () => {
      console.log('Preview field input event, setting retreatHasUnsavedChanges = true');
      retreatHasUnsavedChanges = true;
      const schedule = window.scheduleRetreatAutoSave || scheduleRetreatAutoSave;
      if (typeof schedule === 'function') {
        schedule();
      }
    });
    field.addEventListener('blur', () => {
      // Sync to form on blur
      const fieldId = field.getAttribute('data-field');
      if (fieldId) {
        console.log('Preview field blur, syncing to form:', fieldId);
        // Set flag before syncing to ensure it's not lost
        if (fieldId.startsWith('retreat-')) {
          retreatHasUnsavedChanges = true;
          console.log('Set retreatHasUnsavedChanges = true on blur for:', fieldId);
        }
        syncPreviewToForm(field, fieldId);
        // syncPreviewToForm already schedules auto-save, but we've set the flag above
      }
    });
  });
  
  // Track changes in form fields
  const formFields = [
    'retreat-hero-title', 'retreat-hero-subtitle',
    'retreat-locations-title', 'retreat-forest-title', 'retreat-forest-description',
    'retreat-indoor-title', 'retreat-indoor-description', 'retreat-theatre-title',
    'retreat-theatre-description',
    'retreat-contact-title', 'retreat-contact-text',
    'retreat-collaboration-title',
    'retreat-collaboration-intro'
  ];
  
  formFields.forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
      field.addEventListener('input', () => {
        retreatHasUnsavedChanges = true;
        scheduleRetreatAutoSave();
      });
    }
  });
  
  // Warn before leaving page with unsaved changes
  // Only add listener once
  if (!window.retreatBeforeUnloadAdded) {
    window.addEventListener('beforeunload', (e) => {
      if (retreatHasUnsavedChanges && !retreatIsSaving) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave this page?';
        return e.returnValue;
      }
    });
    window.retreatBeforeUnloadAdded = true;
  }
}

async function autoSaveRetreatContent() {
  console.log('autoSaveRetreatContent called, retreatIsSaving:', retreatIsSaving, 'retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  
  if (retreatIsSaving) {
    // If already saving, reschedule
    console.log('Already saving, rescheduling...');
    const schedule = window.scheduleRetreatAutoSave || scheduleRetreatAutoSave;
    if (typeof schedule === 'function') {
      schedule();
    }
    return;
  }
  
  // Re-check flag after a small delay to see if it was reset
  // Sometimes the flag gets reset between scheduling and execution
  await new Promise(resolve => setTimeout(resolve, 50));
  console.log('After 50ms delay, retreatHasUnsavedChanges:', retreatHasUnsavedChanges);
  
  if (!retreatHasUnsavedChanges) {
    console.log('No unsaved changes, skipping auto-save. Flag was reset somewhere.');
    console.trace('Stack trace for debugging:');
    return;
  }
  
  console.log('Starting auto-save...');
  retreatIsSaving = true;
  retreatSaveRetryCount = 0;
  const updateStatus = window.updateRetreatSaveStatus || updateRetreatSaveStatus;
  if (typeof updateStatus === 'function') {
    updateStatus('Saving...', '⏳');
  }
  
  await saveRetreatContentWithRetry();
  
  retreatIsSaving = false;
  console.log('Auto-save completed');
}

// Make it globally accessible
window.autoSaveRetreatContent = autoSaveRetreatContent;

async function saveRetreatContentWithRetry() {
  console.log('saveRetreatContentWithRetry called, attempt:', retreatSaveRetryCount + 1);
  try {
    // Sync all preview fields to form before saving
    console.log('Syncing preview fields to form...');
    document.querySelectorAll('#retreat-workshop-section .editable-preview').forEach(previewEl => {
      const fieldId = previewEl.getAttribute('data-field');
      if (fieldId) {
        syncPreviewToForm(previewEl, fieldId);
      }
    });
    
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Hero section
    formData.append('retreat_hero_title', document.getElementById('retreat-hero-title')?.value || '');
    formData.append('retreat_hero_subtitle', document.getElementById('retreat-hero-subtitle')?.value || '');
    
    // Locations section
    formData.append('retreat_locations_title', document.getElementById('retreat-locations-title')?.value || '');
    
    // Forest Platforms card
    formData.append('retreat_forest_title', document.getElementById('retreat-forest-title')?.value || '');
    formData.append('retreat_forest_description', document.getElementById('retreat-forest-description')?.value || '');
    formData.append('retreat_forest_list_label', '');
    formData.append('retreat_forest_list_items', '');
    const forestGalleryField = document.getElementById('retreat-forest-gallery');
    if (forestGalleryField) {
      const galleryValue = forestGalleryField.value || '[]';
      console.log('Saving forest gallery:', galleryValue);
      formData.append('retreat_forest_gallery', galleryValue);
    } else {
      console.error('Forest gallery field not found when saving!');
    }
    
    // Indoor Space card
    formData.append('retreat_indoor_title', document.getElementById('retreat-indoor-title')?.value || '');
    formData.append('retreat_indoor_description', document.getElementById('retreat-indoor-description')?.value || '');
    formData.append('retreat_indoor_additional', '');
    const indoorGalleryField = document.getElementById('retreat-indoor-gallery');
    if (indoorGalleryField) {
      const galleryValue = indoorGalleryField.value || '[]';
      console.log('Saving indoor gallery:', galleryValue);
      formData.append('retreat_indoor_gallery', galleryValue);
    } else {
      console.error('Indoor gallery field not found when saving!');
    }
    
    // Home Theatre card
    formData.append('retreat_theatre_title', document.getElementById('retreat-theatre-title')?.value || '');
    formData.append('retreat_theatre_description', document.getElementById('retreat-theatre-description')?.value || '');
    const theatreGalleryField = document.getElementById('retreat-theatre-gallery');
    if (theatreGalleryField) {
      const galleryValue = theatreGalleryField.value || '[]';
      console.log('Saving theatre gallery:', galleryValue);
      formData.append('retreat_theatre_gallery', galleryValue);
    } else {
      console.error('Theatre gallery field not found when saving!');
    }
    
    // Contact Form section
    formData.append('retreat_contact_title', document.getElementById('retreat-contact-title')?.value || '');
    formData.append('retreat_contact_text', document.getElementById('retreat-contact-text')?.value || '');
    
    formData.append('retreat_organizer_title', '');
    formData.append('retreat_workshops_title', '');
    formData.append('retreat_workshops_intro', '');
    formData.append('retreat_workshops_list', '');
    formData.append('retreat_workshops_conclusion', '');
    
    // Collaboration section
    formData.append('retreat_collaboration_title', document.getElementById('retreat-collaboration-title')?.value || '');
    formData.append('retreat_collaboration_intro', document.getElementById('retreat-collaboration-intro')?.value || '');
    formData.append('retreat_collaboration_list', '');
    formData.append('retreat_collaboration_conclusion', '');
    
    console.log('Sending save request to api.php...');
    // Log all form data being sent (especially gallery fields)
    console.log('FormData contents (gallery fields):');
    for (let pair of formData.entries()) {
      if (pair[0].includes('gallery')) {
        console.log(`  ${pair[0]}:`, pair[1], '(type:', typeof pair[1], ')');
      }
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    const rawText = await response.text();
    const result = parseJsonFromText(rawText);
    console.log('Response status:', response.status, 'body:', result);

    if (response.ok && isApiSaveSuccess(result)) {
      console.log('Auto-save successful!');
      retreatHasUnsavedChanges = false;
      retreatSaveRetryCount = 0;
      const retreatContent = {
        retreatHeroTitle: document.getElementById('retreat-hero-title')?.value || '',
        retreatHeroSubtitle: document.getElementById('retreat-hero-subtitle')?.value || '',
        retreatLocationsTitle: document.getElementById('retreat-locations-title')?.value || '',
        retreatForestTitle: document.getElementById('retreat-forest-title')?.value || '',
        retreatForestDescription: document.getElementById('retreat-forest-description')?.value || '',
        retreatIndoorTitle: document.getElementById('retreat-indoor-title')?.value || '',
        retreatIndoorDescription: document.getElementById('retreat-indoor-description')?.value || '',
        retreatTheatreTitle: document.getElementById('retreat-theatre-title')?.value || '',
        retreatTheatreDescription: document.getElementById('retreat-theatre-description')?.value || '',
        retreatContactTitle: document.getElementById('retreat-contact-title')?.value || '',
        retreatContactText: document.getElementById('retreat-contact-text')?.value || '',
        retreatCollaborationTitle: document.getElementById('retreat-collaboration-title')?.value || '',
        retreatCollaborationIntro: document.getElementById('retreat-collaboration-intro')?.value || ''
      };
      localStorage.setItem('btb_retreat_workshop_content', JSON.stringify(retreatContent));
      if (typeof updateRetreatSaveStatus === 'function') {
        updateRetreatSaveStatus('Saved', '✓');
        setTimeout(() => {
          if (!retreatHasUnsavedChanges) {
            updateRetreatSaveStatus('', '');
          }
        }, 3000);
      }
      updateRetreatPreview(buildRetreatDataFromFormForPreview());
    } else {
      const errMsg = getApiSaveErrorMessage(result, response, 'Save failed');
      throw new Error(errMsg);
    }
  } catch (error) {
    console.error('Auto-save error:', error);
    retreatSaveRetryCount++;
    const errDetail = (error && error.message) ? String(error.message) : 'Save error';
    if (retreatSaveRetryCount < RETREAT_MAX_RETRIES) {
      const short = errDetail.length > 60 ? errDetail.slice(0, 60) + '…' : errDetail;
      updateRetreatSaveStatus(`Error. Retry… (${retreatSaveRetryCount}/${RETREAT_MAX_RETRIES}) ` + short, '⚠️');
      setTimeout(() => {
        saveRetreatContentWithRetry();
      }, 1000);
    } else {
      const finalMsg = 'Not saved: ' + errDetail;
      const show = finalMsg.length > 120 ? finalMsg.slice(0, 120) + '…' : finalMsg;
      updateRetreatSaveStatus(show, '❌');
      retreatHasUnsavedChanges = true;
    }
  }
}

// Make saveRetreatContentWithRetry globally accessible
window.saveRetreatContentWithRetry = saveRetreatContentWithRetry;

function updateRetreatSaveStatus(text, icon) {
  console.log('updateRetreatSaveStatus called with:', text, icon);
  if (text != null && String(text).trim() === '') {
    const statusTextClear = document.getElementById('retreat-save-status-text');
    const statusIconClear = document.getElementById('retreat-save-status-icon');
    if (statusTextClear) {
      statusTextClear.textContent = '';
    }
    if (statusIconClear) {
      statusIconClear.textContent = '';
    }
    hideAdminGlobalSaveBar();
    return;
  }
  const statusText = document.getElementById('retreat-save-status-text');
  const statusIcon = document.getElementById('retreat-save-status-icon');
  if (!statusText) {
    console.warn('retreat-save-status-text element not found!');
    updateAdminGlobalRetreatSaveBar(text, icon);
    return;
  }
  if (!statusIcon) {
    console.warn('retreat-save-status-icon element not found!');
  }
  statusText.textContent = text;
  if (text === 'Saved') {
    statusText.style.color = '#10b981';
  } else if (String(text).includes('Error')) {
    statusText.style.color = '#ef4444';
  } else if (text === 'Saving...') {
    statusText.style.color = '#3b82f6';
  } else {
    statusText.style.color = '#6b7280';
  }
  if (statusIcon) {
    statusIcon.textContent = icon;
  }
  updateAdminGlobalRetreatSaveBar(text, icon);
  console.log('Status updated:', text);
}

// Make it globally accessible
window.updateRetreatSaveStatus = updateRetreatSaveStatus;

  // Special — same payload as auto-save (includes localStorage images)
  const saveSpecialBtn = document.getElementById('save-special');
  if (saveSpecialBtn) {
    saveSpecialBtn.addEventListener('click', () => {
      if (typeof specialHasUnsavedChanges !== 'undefined') {
        specialHasUnsavedChanges = true;
      }
      saveSpecialContent();
    });
  }

  // About — same as auto-save (includes hero / founder images from localStorage)
  const saveAboutBtn = document.getElementById('save-about');
  if (saveAboutBtn) {
    saveAboutBtn.addEventListener('click', () => {
      if (typeof aboutHasUnsavedChanges !== 'undefined') {
        aboutHasUnsavedChanges = true;
      }
      saveAboutContent();
    });
  }
}

// Homepage rooms auto-save functionality
let homepageRoomsAutoSaveTimer = null;
let homepageRoomsHasUnsavedChanges = false;

function scheduleHomepageRoomsAutoSave() {
  if (homepageRoomsAutoSaveTimer) {
    clearTimeout(homepageRoomsAutoSaveTimer);
  }
  
  homepageRoomsAutoSaveTimer = setTimeout(() => {
    if (homepageRoomsHasUnsavedChanges) {
      saveHomepageRoomsContent();
      homepageRoomsHasUnsavedChanges = false;
    }
  }, 2000); // 2 second delay
  
  updateHomepageRoomsSaveStatus('saving');
}

// Make scheduleHomepageRoomsAutoSave globally accessible
window.scheduleHomepageRoomsAutoSave = scheduleHomepageRoomsAutoSave;

async function saveHomepageRoomsContent() {
  updateHomepageRoomsSaveStatus('saving');
  
  try {
    // Sync all preview fields to hidden form fields before saving
    const previewFields = [
      'rooms-title', 'rooms-subtitle',
      'room-basement-card-title', 'room-basement-card-description',
      'room-ground-queen-card-title', 'room-ground-queen-card-description',
      'room-ground-twin-card-title', 'room-ground-twin-card-description',
      'room-second-card-title', 'room-second-card-description'
    ];
    
    previewFields.forEach(fieldName => {
      const previewElement = document.getElementById('preview-' + fieldName);
      if (previewElement) {
        syncPreviewToForm(previewElement, fieldName);
      }
    });
    
    const formData = new FormData();
    formData.append('action', 'save_content');
    
    // Get all field values
    const roomsTitle = document.getElementById('rooms-title')?.value || 'Choose your room';
    const roomsSubtitle = document.getElementById('rooms-subtitle')?.value || '';
    
    const basementCardTitle = document.getElementById('room-basement-card-title')?.value || '';
    const basementCardDescription = document.getElementById('room-basement-card-description')?.value || '';
    
    const groundQueenCardTitle = document.getElementById('room-ground-queen-card-title')?.value || '';
    const groundQueenCardDescription = document.getElementById('room-ground-queen-card-description')?.value || '';
    
    const groundTwinCardTitle = document.getElementById('room-ground-twin-card-title')?.value || '';
    const groundTwinCardDescription = document.getElementById('room-ground-twin-card-description')?.value || '';
    
    const secondCardTitle = document.getElementById('room-second-card-title')?.value || '';
    const secondCardDescription = document.getElementById('room-second-card-description')?.value || '';
    
    console.log('Saving homepage rooms content:', {
      rooms_title: roomsTitle,
      rooms_subtitle: roomsSubtitle,
      room_basement_card_title: basementCardTitle,
      room_basement_card_description: basementCardDescription,
      room_ground_queen_card_title: groundQueenCardTitle,
      room_ground_queen_card_description: groundQueenCardDescription,
      room_ground_twin_card_title: groundTwinCardTitle,
      room_ground_twin_card_description: groundTwinCardDescription,
      room_second_card_title: secondCardTitle,
      room_second_card_description: secondCardDescription
    });
    
    formData.append('rooms_title', roomsTitle);
    formData.append('rooms_subtitle', roomsSubtitle);
    formData.append('room_basement_card_title', basementCardTitle);
    formData.append('room_basement_card_description', basementCardDescription);
    
    formData.append('room_ground_queen_card_title', groundQueenCardTitle);
    formData.append('room_ground_queen_card_description', groundQueenCardDescription);
    
    formData.append('room_ground_twin_card_title', groundTwinCardTitle);
    formData.append('room_ground_twin_card_description', groundTwinCardDescription);
    
    formData.append('room_second_card_title', secondCardTitle);
    formData.append('room_second_card_description', secondCardDescription);
    
    // Get image URLs from localStorage (they're saved there by upload_image.php)
    const stored = localStorage.getItem('btb_homepage_rooms') || '{}';
    const storedJson = JSON.parse(stored);
    
    formData.append('room_basement_card_image_url', storedJson.basement?.imageUrl || '');
    formData.append('room_ground_queen_card_image_url', storedJson.groundQueen?.imageUrl || '');
    formData.append('room_ground_twin_card_image_url', storedJson.groundTwin?.imageUrl || '');
    formData.append('room_second_card_image_url', storedJson.second?.imageUrl || '');
    
    const { ok, result, error: saveErr } = await postApiFormDataAndUpdateStatus('homepage-rooms', formData);
    if (ok) {
      console.log('Homepage rooms content saved successfully:', result);
      if (result && result.warning) {
        console.warn('Warning:', result.warning);
        alert('Warning: ' + result.warning + '\n\nPlease run add_rooms_title_fields.php on the server to add required database columns.');
      }
      const stored = localStorage.getItem('btb_content') || '{}';
      const cj = JSON.parse(stored);
      cj.roomsTitle = roomsTitle;
      cj.roomsSubtitle = roomsSubtitle;
      localStorage.setItem('btb_content', JSON.stringify(cj));
    } else {
      const errorMessage = saveErr || 'Unknown save error';
      if (errorMessage.includes('Database columns missing') || errorMessage.includes('rooms_title') || errorMessage.includes('rooms_subtitle')) {
        alert('Error: Required database columns are missing. Please run add_rooms_title_fields.php to create them.');
      } else {
        alert('Save error: ' + errorMessage);
      }
    }
  } catch (error) {
    console.error('Error saving homepage rooms content:', error);
    updateHomepageRoomsSaveStatus('error', (error && error.message) || 'Save failed');
    alert('Save error: ' + (error && error.message));
  }
}

function updateHomepageRoomsSaveStatus(status, detail) {
  updateAdminSectionSaveStatus('homepage-rooms', status, detail);
}

function initHomepageRoomsAutoSave() {
  // syncPreviewToForm already handles triggering auto-save for homepage-rooms fields
  // This function is here for consistency with other pages
  console.log('Homepage rooms auto-save initialized');
}

// Initialize save handler for homepage rooms (legacy - now using auto-save)
function initHomepageRoomsSaveHandler() {
  // Old save button removed - now using auto-save
}


// Initialize admin panel
registerContentEditor('retreat-workshop', () => {
  loadRetreatWorkshopData();
  loadRetreatImagesData();
  initRetreatImageUpload();
  initRetreatSaveHandler();
  initRetreatHelperUI();
  initRetreatLocationGalleries();
  setTimeout(() => {
    if (typeof initRetreatAutoSave === 'function') {
      initRetreatAutoSave();
    }
  }, 100);
});

registerContentEditor('homepage', () => {
  loadHomepageData();
  loadHomepageImagesData();
  initHomepageImageUpload();
  initHomepageAutoSave();
});

registerContentEditor('homepage-rooms', () => {
  loadHomepageRoomsData();
  initHomepageRoomsImageUpload();
  initHomepageRoomsAutoSave();
});

registerContentEditor('special', () => {
  loadSpecialData();
  loadSpecialImagesData();
  initSpecialImageUpload();
  initSpecialAutoSave();
});

registerContentEditor('explore', () => {
  loadExploreData();
  initExploreImageUpload();
  initExploreParkCardsToolbar();
  initExploreSectionCardsToolbar();
  initAboutAttractionGalleries();
  initParkCardGalleryUploads();
  initExploreSectionCardGalleryUploads();
  initExploreAutoSave();
});

registerContentEditor('about', () => {
  loadAboutData();
  loadAboutImagesData();
  initAboutImageUpload();
  initAboutAutoSave();
});

registerContentEditor('contact', () => {
  loadContactData();
  initContactAutoSave();
});

registerContentEditor('massage', () => {
  loadMassageData();
  loadMassageImagesData();
  initMassageImageUpload();
  initMassageAutoSave();
});

registerContentEditor('room-second', () => {
  loadRoomSecondData();
  initRoomSecondImageUpload();
  initRoomSecondAutoSave();
});

registerContentEditor('room-ground-twin', () => {
  loadRoomGroundTwinData();
  initRoomGroundTwinImageUpload();
  initRoomGroundTwinAutoSave();
});

registerContentEditor('room-ground-queen', () => {
  loadRoomGroundQueenData();
  initRoomGroundQueenImageUpload();
  initRoomGroundQueenAutoSave();
});

registerContentEditor('room-basement', () => {
  loadRoomBasementData();
  initRoomBasementImageUpload();
  initRoomBasementAutoSave();
});

let guestReviewsSaveTimer = null;

const GUEST_REVIEWS_FIELDS_DOM_VER = '2';

function ensureGuestReviewsFieldsDom() {
  const vr = document.getElementById('guest-reviews-vrbo-fields');
  const ar = document.getElementById('guest-reviews-airbnb-fields');
  if (!vr || !ar) {
    return;
  }
  if (vr.getAttribute('data-btb-built') === GUEST_REVIEWS_FIELDS_DOM_VER) {
    return;
  }
  vr.innerHTML = '';
  ar.innerHTML = '';
  function makeBlock(prefix, i) {
    const w = document.createElement('div');
    w.className = 'guest-reviews-review-card';
    w.style.cssText =
      'margin-bottom:12px;padding:12px;background:#f8fafc;border:1px solid #d1d5db;border-radius:8px;';
    const idBase = 'guest-review-' + prefix;
    const src = prefix === 'vrbo' ? 'Vrbo' : 'Airbnb';
    const n = i + 1;
    w.innerHTML =
      '<div class="guest-reviews-review-index" aria-hidden="true">' +
      'Review ' +
      n +
      '</div>' +
      '<div style="display:flex;flex-direction:column;gap:8px;">' +
      '<input type="text" id="' +
      idBase +
      '-name-' +
      i +
      '" class="retreat-text-input" autocomplete="off" ' +
      'aria-label="' +
      src +
      ' review ' +
      n +
      ' — name" ' +
      'placeholder="" />' +
      '<select id="' +
      idBase +
      '-rating-' +
      i +
      '" class="retreat-text-input" style="max-width:6rem;" ' +
      'aria-label="' +
      src +
      ' review ' +
      n +
      ' — star rating 1 to 5">' +
      [1, 2, 3, 4, 5]
        .map(
          (rv) =>
            '<option value="' + rv + '"' + (rv === 5 ? ' selected' : '') + '>' + rv + '</option>',
        )
        .join('') +
      '</select>' +
      '<textarea id="' +
      idBase +
      '-text-' +
      i +
      '" rows="3" class="retreat-text-input" style="min-height:4rem;resize:vertical;" ' +
      'aria-label="' +
      src +
      ' review ' +
      n +
      ' — text" placeholder=""></textarea>' +
      '</div>';
    return w;
  }
  for (let i = 0; i < 5; i++) {
    vr.appendChild(makeBlock('vrbo', i));
    ar.appendChild(makeBlock('airbnb', i));
  }
  vr.setAttribute('data-btb-built', GUEST_REVIEWS_FIELDS_DOM_VER);
  ar.setAttribute('data-btb-built', GUEST_REVIEWS_FIELDS_DOM_VER);
}

async function loadGuestReviewsData() {
  ensureGuestReviewsFieldsDom();
  try {
    const res = await fetch('api.php?action=get_guest_reviews');
    const json = await res.json();
    if (!json.success || !json.data) {
      updateAdminSectionSaveStatus('guest-reviews', 'error', (json && json.error) || 'Load failed');
      return;
    }
    const d = json.data;
    const titleEl = document.getElementById('guest-reviews-title');
    const subEl = document.getElementById('guest-reviews-subtitle');
    if (titleEl) {
      titleEl.value = d.section_title || '';
    }
    if (subEl) {
      subEl.value = d.section_subtitle || '';
    }
    ['vrbo', 'airbnb'].forEach((prefix) => {
      const list = d[prefix] || [];
      for (let i = 0; i < 5; i++) {
        const item = list[i] || {};
        const n = document.getElementById('guest-review-' + prefix + '-name-' + i);
        const t = document.getElementById('guest-review-' + prefix + '-text-' + i);
        const r = document.getElementById('guest-review-' + prefix + '-rating-' + i);
        if (n) {
          n.value = item.name || '';
        }
        if (t) {
          t.value = item.text || '';
        }
        if (r) {
          r.value = String(item.rating != null ? item.rating : 5);
        }
      }
    });
  } catch (e) {
    updateAdminSectionSaveStatus('guest-reviews', 'error', (e && e.message) || 'Error');
  }
}

function collectGuestReviewsList(prefix) {
  const a = [];
  for (let i = 0; i < 5; i++) {
    const n = document.getElementById('guest-review-' + prefix + '-name-' + i);
    const t = document.getElementById('guest-review-' + prefix + '-text-' + i);
    const r = document.getElementById('guest-review-' + prefix + '-rating-' + i);
    a.push({
      name: n && n.value ? n.value.trim() : '',
      text: t && t.value ? t.value.trim() : '',
      rating: Math.max(1, Math.min(5, parseInt((r && r.value) || '5', 10) || 5)),
    });
  }
  return a;
}

async function saveGuestReviewsContent() {
  updateAdminSectionSaveStatus('guest-reviews', 'saving');
  const formData = new FormData();
  formData.append('action', 'save_guest_reviews');
  formData.append('section_title', document.getElementById('guest-reviews-title')?.value || '');
  formData.append('section_subtitle', document.getElementById('guest-reviews-subtitle')?.value || '');
  formData.append('vrbo_reviews', JSON.stringify(collectGuestReviewsList('vrbo')));
  formData.append('airbnb_reviews', JSON.stringify(collectGuestReviewsList('airbnb')));
  try {
    const response = await fetch('api.php', { method: 'POST', body: formData });
    const raw = await response.text();
    const result = parseJsonFromText(raw);
    if (response.ok && result && result.success) {
      updateAdminSectionSaveStatus('guest-reviews', 'saved');
    } else {
      updateAdminSectionSaveStatus(
        'guest-reviews',
        'error',
        (result && (result.error || result.message)) || 'Save failed',
      );
    }
  } catch (e) {
    updateAdminSectionSaveStatus('guest-reviews', 'error', (e && e.message) || 'Error');
  }
}

function initGuestReviewsAutoSave() {
  const root = document.getElementById('guest-reviews-section');
  if (!root || root.getAttribute('data-btb-guest-reviews-autosave') === '1') {
    return;
  }
  root.setAttribute('data-btb-guest-reviews-autosave', '1');
  const schedule = () => {
    if (guestReviewsSaveTimer) {
      clearTimeout(guestReviewsSaveTimer);
    }
    guestReviewsSaveTimer = setTimeout(() => {
      saveGuestReviewsContent();
    }, 1800);
  };
  root.addEventListener('input', schedule, true);
  root.addEventListener('change', schedule, true);
}

registerContentEditor('floorplan', () => {
  console.log('registerContentEditor: floorplan initializer called');
  // Reset initialization flag when switching to floorplan section
  window.floorplanGalleriesInitialized = false;
  loadFloorplanData();
  initFloorplanImageUpload();
  initFloorplanAutoSave();
  // Initialize gallery management after a short delay to ensure DOM is ready
  setTimeout(() => {
    if (typeof initFloorplanGalleries === 'function') {
      initFloorplanGalleries();
    }
  }, 200);
});

registerContentEditor('guest-reviews', () => {
  loadGuestReviewsData();
  initGuestReviewsAutoSave();
});

registerContentEditor('wellness-experiences', () => {
  loadWellnessExperiencesData();
  initWellnessExperiencesImageUpload();
  initWellnessAutoSave();
});

document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  checkAdminAuth();
  initRoomPagePriceTripletInputsOnce();
  
  // Initialize login form
  initAdminLogin();
  
  // Initialize two-level navigation
  // Primary level tabs (Bookings Management, Content Management, Account Management)
  document.querySelectorAll('.admin-nav-tab-primary').forEach(tab => {
    tab.addEventListener('click', () => {
      const primary = tab.getAttribute('data-primary');
      switchPrimarySection(primary);
    });
  });
  
  // Secondary level tabs (subsections)
  document.querySelectorAll('.admin-nav-tab-secondary').forEach(tab => {
    tab.addEventListener('click', () => {
      const section = tab.getAttribute('data-section');
      showSection(section);
    });
  });
  
  // Initialize default primary section (Bookings Management)
  switchPrimarySection('bookings');
  
  // Initialize logout
  const logoutBtn = document.getElementById('admin-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', adminLogout);
  }
  
  // Initialize save homepage button
  // Old save button removed - now using auto-save
  
  // Initialize save floorplan button
  const saveFloorplanBtn = document.getElementById('save-floorplan');
  if (saveFloorplanBtn) {
    saveFloorplanBtn.addEventListener('click', async () => {
      console.log('Saving floorplan data...');
      // Read current image paths from localStorage or visible path labels to avoid overwriting with empty values
      const stored = localStorage.getItem('btb_floorplan_settings');
      const storedJson = stored ? JSON.parse(stored) : {};
      const basementPathEl = document.getElementById('basement-image-path');
      const groundPathEl = document.getElementById('ground-image-path');
      const loftPathEl = document.getElementById('loft-image-path');

      // Universal: use consistent field names (with fallback for compatibility)
      const currentBasementImage = (basementPathEl && basementPathEl.textContent) || storedJson.basement_image_url || '';
      const currentGroundImage = (groundPathEl && groundPathEl.textContent) || storedJson.ground_image_url || storedJson.ground_queen_image || '';
      const currentLoftImage = (loftPathEl && loftPathEl.textContent) || storedJson.loft_image_url || '';

      const floorplanData = {
        floorplanTitle: document.getElementById('floorplan-title')?.value || 'Common areas',
        floorplanSubtitle: document.getElementById('floorplan-subtitle')?.value || 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.',
        basementSubtitle: document.getElementById('basement-subtitle').value,
        basementDescription: document.getElementById('basement-description').value,
        basementImageUrl: currentBasementImage,
        groundSubtitle: document.getElementById('ground-subtitle').value,
        groundDescription: document.getElementById('ground-description').value,
        groundQueenImage: currentGroundImage,
        groundTwinImage: '',
        loftSubtitle: document.getElementById('loft-subtitle').value,
        loftDescription: document.getElementById('loft-description').value,
        loftImageUrl: currentLoftImage
      };
      
      console.log('Floorplan data to save:', floorplanData);
      
      try {
        const formData = new FormData();
        formData.append('action', 'save_floorplan');
        Object.entries(floorplanData).forEach(([key, value]) => {
          formData.append(key, value || '');
        });
        
        console.log('Sending request to API...');
        const response = await fetch('api.php', {
          method: 'POST',
          body: formData
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('API response:', result);
        
        if (result.success) {
          // Save to localStorage for immediate update on main site
          // Convert camelCase to underscore format for consistency with API
          // Universal: use consistent field names for all sections
          const localStorageData = {
            floorplan_title: floorplanData.floorplanTitle,
            floorplan_subtitle: floorplanData.floorplanSubtitle,
            basement_subtitle: floorplanData.basementSubtitle,
            basement_description: floorplanData.basementDescription,
            basement_image_url: floorplanData.basementImageUrl,
            ground_subtitle: floorplanData.groundSubtitle,
            ground_description: floorplanData.groundDescription,
            ground_image_url: floorplanData.groundQueenImage, // Universal field name
            ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
            ground_twin_image: floorplanData.groundTwinImage,
            loft_subtitle: floorplanData.loftSubtitle,
            loft_description: floorplanData.loftDescription,
            loft_image_url: floorplanData.loftImageUrl
          };
          localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
          console.log('Data saved to localStorage');
          showStatus('Common areas content saved successfully!');
          return;
        } else {
          console.log('API returned error:', result.error);
        }
      } catch (error) {
        console.log('Server save failed:', error);
        // Save to localStorage even if server fails
        // Convert camelCase to underscore format for consistency with API
        // Universal: use consistent field names for all sections
        const localStorageData = {
          floorplan_title: floorplanData.floorplanTitle,
          floorplan_subtitle: floorplanData.floorplanSubtitle,
          basement_subtitle: floorplanData.basementSubtitle,
          basement_description: floorplanData.basementDescription,
          basement_image_url: floorplanData.basementImageUrl,
          ground_subtitle: floorplanData.groundSubtitle,
          ground_description: floorplanData.groundDescription,
          ground_image_url: floorplanData.groundQueenImage, // Universal field name
          ground_queen_image: floorplanData.groundQueenImage, // Keep for compatibility
          ground_twin_image: floorplanData.groundTwinImage,
          loft_subtitle: floorplanData.loftSubtitle,
          loft_description: floorplanData.loftDescription,
          loft_image_url: floorplanData.loftImageUrl
        };
        localStorage.setItem('btb_floorplan_settings', JSON.stringify(localStorageData));
        console.log('Data saved to localStorage as fallback');
      }
      
      showStatus('Common areas content saved successfully!');
    });
  }
  
  // save-contact is registered in initAdminForms() only (avoid duplicate listeners).

  // Initialize save buttons for room pages
  initRoomPageSaveHandlers();
  
  // Initialize save buttons for page content
  initPageContentSaveHandlers();

  // Initialize retreat helper UI (buttons + content status badge)
  initRetreatHelperUI();
  
  // Initialize save buttons for homepage rooms and wellness
  initHomepageRoomsSaveHandler();
  
  // Initialize forms
  initAdminForms();
  
  // Initialize image upload
  initImageUpload();
  
  // Load initial data
  loadSectionData('dashboard');
});

// ==========================================
// BOOKINGS MANAGEMENT
// ==========================================

// Load bookings data
async function loadBookingsData() {
  const loadingEl = document.getElementById('bookings-loading');
  const listEl = document.getElementById('bookings-list');
  const emptyEl = document.getElementById('bookings-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    // Get filters
    const status = document.getElementById('bookings-filter-status')?.value || '';
    const room = document.getElementById('bookings-filter-room')?.value || '';
    
    // Get date values from Flatpickr or native input
    const dateFromInput = document.getElementById('bookings-filter-date-from');
    const dateToInput = document.getElementById('bookings-filter-date-to');
    
    let dateFrom = '';
    let dateTo = '';
    
    if (dateFromInput) {
      if (dateFromInput._flatpickr) {
        dateFrom = dateFromInput._flatpickr.input.value || '';
      } else {
        dateFrom = dateFromInput.value || '';
      }
    }
    
    if (dateToInput) {
      if (dateToInput._flatpickr) {
        dateTo = dateToInput._flatpickr.input.value || '';
      } else {
        dateTo = dateToInput.value || '';
      }
    }
    
    // Load room bookings
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (status) params.append('status', status);
    if (room && room !== 'Massage') params.append('room_name', room);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load bookings');
    }
    
    const result = await response.json();
    
    let allBookings = [];
    
    // Add room bookings
    if (result.success && result.data?.bookings) {
      allBookings = result.data.bookings.map(booking => ({
        ...booking,
        booking_type: 'room'
      }));
    }
    
    // Load massage bookings if "All Rooms" or "Massage" is selected
    if (!room || room === 'Massage') {
      try {
        console.log('Loading massage bookings...', { status, dateFrom, dateTo });
        const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
        if (status) massageParams.append('status', status);
        if (dateFrom) massageParams.append('date_from', dateFrom);
        if (dateTo) massageParams.append('date_to', dateTo);
        
        console.log('Massage bookings request URL:', 'api.php?' + massageParams.toString());
        
        const massageResponse = await fetch('api.php?' + massageParams.toString(), {
          method: 'GET'
        });
        
        console.log('Massage bookings response status:', massageResponse.status, massageResponse.ok);
        
        if (massageResponse.ok) {
          const massageContentType = massageResponse.headers.get('content-type');
          console.log('Massage bookings content-type:', massageContentType);
          
          if (massageContentType && massageContentType.includes('application/json')) {
            try {
              const massageResult = await massageResponse.json();
              console.log('Massage bookings result:', massageResult);
              
              if (massageResult.success && massageResult.data?.bookings) {
                // API уже отфильтровал по статусу и дате, используем все полученные бронирования
                let massageBookings = massageResult.data.bookings;
                console.log('Massage bookings count:', massageBookings.length);
                
                // Convert massage bookings to unified format
                const convertedMassageBookings = massageBookings.map(booking => ({
                  id: booking.id,
                  booking_type: 'massage',
                  room_name: 'Massage',
                  massage_date: booking.massage_date,
                  massage_time: booking.massage_time,
                  massage_type: booking.massage_type,
                  duration: booking.duration,
                  guest_name: booking.guest_name,
                  email: booking.email,
                  phone: booking.phone,
                  status: booking.status,
                  created_at: booking.created_at,
                  confirmation_code: booking.confirmation_code || `MASS-${booking.id}`
                }));
                
                console.log('Converted massage bookings:', convertedMassageBookings);
                allBookings = [...allBookings, ...convertedMassageBookings];
              } else {
                console.warn('Massage bookings result:', massageResult);
              }
            } catch (jsonError) {
              console.error('Failed to parse massage bookings JSON:', jsonError);
            }
          } else {
            const text = await massageResponse.text();
            console.error('API returned non-JSON response for massage bookings:', text.substring(0, 200));
          }
        } else {
          const errorText = await massageResponse.text();
          console.warn('Failed to load massage bookings: HTTP', massageResponse.status, errorText.substring(0, 200));
        }
      } catch (massageError) {
        console.error('Failed to load massage bookings:', massageError);
        // Continue without massage bookings
      }
    }
    
    // Sort by created_at (newest first)
    allBookings.sort((a, b) => {
      const dateA = new Date(a.created_at || 0);
      const dateB = new Date(b.created_at || 0);
      return dateB - dateA;
    });
    
    if (loadingEl) loadingEl.style.display = 'none';
    
    if (allBookings.length > 0) {
      if (listEl) {
        listEl.style.display = 'block';
        renderBookingsList(allBookings);
      }
    } else {
      if (emptyEl) emptyEl.style.display = 'block';
    }
  } catch (error) {
    console.error('Load bookings error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'block';
    showStatus('Failed to load bookings: ' + error.message, 'error');
  }
}

// Render bookings list
function renderBookingsList(bookings) {
  const listEl = document.getElementById('bookings-list');
  if (!listEl) return;
  
  listEl.innerHTML = '';
  
  bookings.forEach(booking => {
    const card = document.createElement('div');
    card.className = 'booking-card';
    
    // Determine status display based on booking status and payment status
    let statusText = '';
    let statusClass = booking.status || 'pending';
    
    if (booking.booking_type === 'massage') {
      // Massage booking status logic
      if (booking.status === 'pending') {
        statusText = 'Awaiting Confirmation';
        statusClass = 'pending';
      } else if (booking.status === 'cancelled') {
        statusText = 'Rejected';
        statusClass = 'cancelled';
      } else if (booking.status === 'confirmed') {
        // Check if massage date has passed
        const massageDate = new Date(booking.massage_date + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (massageDate < today) {
          statusText = 'Completed';
          statusClass = 'completed';
        } else {
          statusText = 'Confirmed';
          statusClass = 'confirmed';
        }
      } else if (booking.status === 'completed') {
        statusText = 'Completed';
        statusClass = 'completed';
      } else {
        statusText = booking.status || 'Pending';
      }
    } else {
      // Room booking status logic
      if (booking.status === 'pending') {
        statusText = 'Awaiting Confirmation';
        statusClass = 'pending';
      } else if (booking.status === 'cancelled') {
        statusText = 'Rejected';
        statusClass = 'cancelled';
      } else if (booking.status === 'confirmed') {
        if (booking.payment_status === 'paid') {
          // Check if check-in date has passed
          const checkinDate = new Date(booking.checkin_date + 'T00:00:00');
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          
          if (checkinDate > today) {
            statusText = 'Awaiting Check-in';
            statusClass = 'checked_in';
          } else {
            // Check if checkout date has passed
            const checkoutDate = new Date(booking.checkout_date + 'T00:00:00');
            if (checkoutDate < today) {
              statusText = 'Completed';
              statusClass = 'completed';
            } else {
              statusText = 'Awaiting Check-in';
              statusClass = 'checked_in';
            }
          }
        } else {
          statusText = 'Awaiting Payment';
          statusClass = 'confirmed';
        }
      } else {
        statusText = booking.status || 'Pending';
      }
    }
    
    const paymentStatusClass = booking.payment_status || 'pending';
    
    card.innerHTML = `
      <div class="booking-card-header">
        <div>
          <h3 style="margin: 0 0 8px 0;">Booking #${booking.id || '—'}</h3>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span class="booking-status-badge ${statusClass}">${statusText}</span>
            ${booking.payment_status === 'paid' ? `<span class="payment-status-badge paid">Paid</span>` : ''}
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 12px; color: #718096; margin-bottom: 4px;">Confirmation Code</div>
          <div style="font-family: monospace; font-weight: 600;">${booking.confirmation_code || '—'}</div>
        </div>
      </div>
      
      <div class="booking-details-grid">
        ${booking.booking_type === 'massage' ? `
          <div class="booking-detail-item">
            <div class="booking-detail-label">Service</div>
            <div class="booking-detail-value">${escapeHtml(booking.room_name || 'Massage')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Date</div>
            <div class="booking-detail-value">${formatDate(booking.massage_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Time</div>
            <div class="booking-detail-value">${booking.massage_time || '—'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Type</div>
            <div class="booking-detail-value">${escapeHtml(booking.massage_type || '—')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Duration</div>
            <div class="booking-detail-value">${booking.duration ? `${booking.duration} min` : '—'}</div>
          </div>
        ` : `
          <div class="booking-detail-item">
            <div class="booking-detail-label">Room</div>
            <div class="booking-detail-value">${escapeHtml(booking.room_name || '—')}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Check-in</div>
            <div class="booking-detail-value">${formatDate(booking.checkin_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Check-out</div>
            <div class="booking-detail-value">${formatDate(booking.checkout_date)}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Guests</div>
            <div class="booking-detail-value">${booking.guests_count || '—'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Pets</div>
            <div class="booking-detail-value">${booking.pets ? 'Yes' : 'No'}</div>
          </div>
          <div class="booking-detail-item">
            <div class="booking-detail-label">Total Amount</div>
            <div class="booking-detail-value">${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}</div>
          </div>
        `}
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guest Name</div>
          <div class="booking-detail-value">${escapeHtml(booking.guest_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Email</div>
          <div class="booking-detail-value">${escapeHtml(booking.email || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Phone</div>
          <div class="booking-detail-value">${escapeHtml(booking.phone || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Created</div>
          <div class="booking-detail-value">${formatDateTime(booking.created_at)}</div>
        </div>
      </div>
      
      <div class="booking-actions">
        ${booking.booking_type === 'massage' ? `
          ${booking.status === 'pending' ? `
            <button class="admin-btn admin-btn-primary" onclick="window.confirmMassageBooking(${booking.id})">Confirm</button>
            <button class="admin-btn admin-btn-danger" onclick="window.cancelMassageBooking(${booking.id})">Reject</button>
            <button class="admin-btn admin-btn-danger" onclick="window.deleteMassageBooking(${booking.id})">Delete</button>
          ` : ''}
          ${booking.status === 'confirmed' ? `
            <button class="admin-btn admin-btn-danger" onclick="window.cancelMassageBooking(${booking.id})">Cancel</button>
          ` : ''}
        ` : `
          ${booking.status === 'pending' ? `
            <button class="admin-btn admin-btn-primary" onclick="window.confirmBooking(${booking.id})">Confirm</button>
            <button class="admin-btn admin-btn-danger" onclick="window.cancelBooking(${booking.id})">Reject</button>
            <button class="admin-btn admin-btn-danger" onclick="window.deleteBooking(${booking.id})">Delete</button>
          ` : ''}
          ${booking.status === 'confirmed' && booking.payment_status !== 'paid' ? `
            <button class="admin-btn admin-btn-danger" onclick="window.cancelBooking(${booking.id})">Cancel</button>
          ` : ''}
          ${booking.status === 'confirmed' ? `
            <button class="admin-btn admin-btn-secondary" onclick="window.viewBookingDetails(${booking.id})">View Details</button>
          ` : ''}
        `}
      </div>
    `;
    
    listEl.appendChild(card);
  });
}

// Confirm booking
async function confirmBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking confirmed successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm booking');
    }
  } catch (error) {
    console.error('Confirm booking error:', error);
    showStatus('Failed to confirm booking: ' + error.message, 'error');
  }
}

// Cancel booking
async function cancelBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking cancelled successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel booking');
    }
  } catch (error) {
    console.error('Cancel booking error:', error);
    showStatus('Failed to cancel booking: ' + error.message, 'error');
  }
}

// Delete booking (only for pending bookings)
async function deleteBooking(bookingId) {
  console.log('deleteBooking called with bookingId:', bookingId);
  
  if (!bookingId || bookingId <= 0) {
    console.error('Invalid booking ID:', bookingId);
    showStatus('Invalid booking ID', 'error');
    return;
  }
  
  // Используем кастомное модальное окно для подтверждения
  return new Promise((resolve) => {
    // Создаем модальное окно
    const modal = document.createElement('div');
    modal.className = 'delete-confirm-modal active';
    modal.innerHTML = `
      <div class="delete-confirm-content">
        <h3>⚠️ Delete Booking #${bookingId}</h3>
        <p>
          <strong>Are you ABSOLUTELY SURE you want to PERMANENTLY DELETE this booking?</strong>
        </p>
        <p style="color: #718096;">
          This will:
          <ul style="margin: 12px 0; padding-left: 20px; color: #718096;">
            <li>Delete the booking from the database</li>
            <li>Remove it from the admin panel</li>
            <li>Remove it from the user account</li>
          </ul>
        </p>
        <p style="color: #e53e3e; font-weight: 600;">
          This action CANNOT be undone!
        </p>
        <div class="delete-confirm-actions">
          <button class="delete-confirm-btn delete-confirm-btn-cancel">Cancel</button>
          <button class="delete-confirm-btn delete-confirm-btn-delete">Delete Permanently</button>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Обработчики кнопок
    const cancelBtn = modal.querySelector('.delete-confirm-btn-cancel');
    const deleteBtn = modal.querySelector('.delete-confirm-btn-delete');
    
    const closeModal = () => {
      modal.classList.remove('active');
      setTimeout(() => {
        document.body.removeChild(modal);
      }, 300);
    };
    
    cancelBtn.addEventListener('click', () => {
      console.log('User cancelled deletion');
      closeModal();
      resolve(false);
    });
    
    deleteBtn.addEventListener('click', async () => {
      console.log('User confirmed deletion, proceeding...');
      closeModal();
      resolve(true);
      
      // Выполняем удаление
      await performDeleteBooking(bookingId);
    });
    
    // Закрытие по клику вне модального окна
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        console.log('User cancelled deletion (clicked outside)');
        closeModal();
        resolve(false);
      }
    });
  });
}

// Функция для выполнения удаления
async function performDeleteBooking(bookingId) {
  try {
    console.log('Starting deletion process for booking:', bookingId);
    
    const formData = new FormData();
    formData.append('action', 'delete_booking');
    formData.append('booking_id', bookingId);
    
    console.log('Sending delete request to api.php');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    console.log('Response received:', response.status, response.statusText);
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Delete booking response error:', response.status, errorText);
      throw new Error(`HTTP ${response.status}: Failed to delete booking`);
    }
    
    const result = await response.json();
    console.log('Delete booking result:', result);
    
    if (result.success) {
      console.log('Booking deleted successfully');
      showStatus('Booking deleted successfully!');
      
      // Reload bookings list
      loadBookingsData();
      updateBookingsDashboardStats();
      
      // Note: Booking is deleted from database
      // If user has it in localStorage, it will be removed when they refresh their account page
      // The booking will no longer appear in their account because it's deleted from the database
    } else {
      console.error('Delete booking failed:', result.error);
      throw new Error(result.error || 'Failed to delete booking');
    }
  } catch (error) {
    console.error('Delete booking error:', error);
    showStatus('Failed to delete booking: ' + error.message, 'error');
  }
}

// View booking details
async function viewBookingDetails(bookingId) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to load booking details');
    }
    
    const result = await response.json();
    
    if (result.success && result.data?.booking) {
      const booking = result.data.booking;
      alert(`Booking Details:\n\n` +
        `ID: ${booking.id}\n` +
        `Room: ${booking.room_name}\n` +
        `Check-in: ${formatDate(booking.checkin_date)}\n` +
        `Check-out: ${formatDate(booking.checkout_date)}\n` +
        `Guests: ${booking.guests_count}\n` +
        `Pets: ${booking.pets ? 'Yes' : 'No'}\n` +
        `Guest: ${booking.guest_name}\n` +
        `Email: ${booking.email}\n` +
        `Phone: ${booking.phone}\n` +
        `Total: ${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}\n` +
        `Status: ${booking.status}\n` +
        `Payment: ${booking.payment_status || 'pending'}\n` +
        `Confirmation Code: ${booking.confirmation_code || '—'}\n` +
        `Created: ${formatDateTime(booking.created_at)}\n`
      );
    }
  } catch (error) {
    console.error('View booking details error:', error);
    showStatus('Failed to load booking details: ' + error.message, 'error');
  }
}

// Initialize bookings filters
function initBookingsFilters() {
  const applyBtn = document.getElementById('bookings-filter-apply');
  const resetBtn = document.getElementById('bookings-filter-reset');
  const refreshBtn = document.getElementById('bookings-refresh');
  const dateFromInput = document.getElementById('bookings-filter-date-from');
  const dateToInput = document.getElementById('bookings-filter-date-to');
  
  // Initialize Flatpickr for date filters
  if (typeof flatpickr !== 'undefined') {
    if (dateFromInput && !dateFromInput._flatpickr) {
      // Calculate date range: current year ± 3 years
      const currentYear = new Date().getFullYear();
      const minYear = currentYear - 3;
      const maxYear = currentYear + 3;
      const minDate = new Date(minYear, 0, 1); // January 1st of minYear
      const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
      
      flatpickr(dateFromInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
    
    if (dateToInput && !dateToInput._flatpickr) {
      // Calculate date range: current year ± 3 years
      const currentYear = new Date().getFullYear();
      const minYear = currentYear - 3;
      const maxYear = currentYear + 3;
      const minDate = new Date(minYear, 0, 1); // January 1st of minYear
      const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
      
      flatpickr(dateToInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
  }
  
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      document.getElementById('bookings-filter-status').value = '';
      document.getElementById('bookings-filter-room').value = '';
      
      // Reset Flatpickr dates
      if (dateFromInput) {
        if (dateFromInput._flatpickr) {
          dateFromInput._flatpickr.clear();
        } else {
          dateFromInput.value = '';
        }
      }
      
      if (dateToInput) {
        if (dateToInput._flatpickr) {
          dateToInput._flatpickr.clear();
        } else {
          dateToInput.value = '';
        }
      }
      
      loadBookingsData();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
}

// ==========================================
// CALENDAR MANAGEMENT
// ==========================================

// Load calendar data
async function loadCalendarData() {
  const loadingEl = document.getElementById('admin-calendar-loading');
  const gridEl = document.getElementById('admin-calendar-grid');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (gridEl) gridEl.style.display = 'none';
  
  try {
    // Получаем выбранное бронирование из активной вкладки
    const activeTab = document.querySelector('.calendar-room-tab.active');
    const selectedRoom = activeTab ? activeTab.getAttribute('data-room') : '';
    
    // Если выбрана вкладка "Massage", загружаем только бронирования массажа
    const isMassageTab = selectedRoom === 'Massage';
    
    // Get bookings for selected booking - получаем confirmed и paid бронирования
    // Получаем все бронирования, затем фильтруем на клиенте
    let bookings = [];
    if (!isMassageTab) {
      // Загружаем бронирования комнат только если не выбрана вкладка Massage
      const params = new URLSearchParams({ action: 'get_bookings' });
      if (selectedRoom) {
        params.append('room_name', selectedRoom);
      }
      
      const bookingsResponse = await fetch('api.php?' + params.toString(), {
        method: 'GET'
      });
      
      if (bookingsResponse.ok) {
      // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
      const bookingsContentType = bookingsResponse.headers.get('content-type');
      if (bookingsContentType && bookingsContentType.includes('application/json')) {
        try {
          const bookingsResult = await bookingsResponse.json();
          console.log('Bookings result:', bookingsResult);
          
          if (bookingsResult.success && bookingsResult.data?.bookings) {
            const allBookings = bookingsResult.data.bookings;
            console.log('All bookings before filter:', allBookings.length, allBookings);
            console.log('Booking statuses:', allBookings.map(b => ({ id: b.id, status: b.status, payment_status: b.payment_status })));
            
            // Фильтруем бронирования для календаря - показываем все кроме cancelled
            // (pending, confirmed, completed - все показываем в календаре)
            bookings = allBookings.filter(booking => {
              const isNotCancelled = booking.status !== 'cancelled';
              console.log(`Booking ${booking.id}: status=${booking.status}, isNotCancelled=${isNotCancelled}`);
              return isNotCancelled;
            });
            console.log('Filtered bookings for calendar:', bookings.length, bookings);
          } else {
            console.warn('No bookings in result:', bookingsResult);
          }
        } catch (jsonError) {
          console.error('Failed to parse bookings JSON:', jsonError);
          // Продолжаем без бронирований
        }
      } else {
        const text = await bookingsResponse.text();
        console.error('API returned non-JSON response for bookings:', text.substring(0, 200));
        // Продолжаем без бронирований
      }
    }
    }
    
    // Get blocked dates (manual blocking)
    const blockedParams = new URLSearchParams({ action: 'get_blocked_dates' });
    if (selectedRoom && !isMassageTab) {
      blockedParams.append('room_name', selectedRoom);
    }
    
    const blockedResponse = await fetch('api.php?' + blockedParams.toString(), {
      method: 'GET'
    });
    
    // Get Airbnb sync status (for Airbnb blocked dates) - только для комнат, не для массажа
    const airbnbParams = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    if (selectedRoom && !isMassageTab) {
      airbnbParams.append('room_name', selectedRoom);
    }
    
    const airbnbResponse = await fetch('api.php?' + airbnbParams.toString(), {
      method: 'GET'
    });
    
    let blockedDates = [];
    if (blockedResponse.ok) {
      // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
      const blockedContentType = blockedResponse.headers.get('content-type');
      if (blockedContentType && blockedContentType.includes('application/json')) {
        try {
          const blockedResult = await blockedResponse.json();
          if (blockedResult.success) {
        // Получаем ручные блокировки (периоды)
        if (blockedResult.data?.blocked_dates) {
          // Преобразуем периоды в список дат для календаря
          blockedResult.data.blocked_dates.forEach(blocked => {
            // Учитываем блокировки для конкретной комнаты и блокировки "__all__" (для всех)
            // Если выбрана конкретная комната, показываем блокировки этой комнаты и "__all__"
            // Если выбрана вкладка "All Bookings", показываем все блокировки
            const isRelevant = !selectedRoom || blocked.room_name === selectedRoom || blocked.room_name === '__all__';
            
            if (isRelevant) {
              const dateFrom = blocked.date_from || blocked.blocked_date || '';
              const dateTo = blocked.date_to || blocked.blocked_date || '';
              
              if (dateFrom && dateTo) {
                // Генерируем все даты в периоде
                const fromDate = new Date(dateFrom);
                const toDate = new Date(dateTo);
                
                for (let d = new Date(fromDate); d <= toDate; d.setDate(d.getDate() + 1)) {
                  const dateStr = d.toISOString().split('T')[0];
                  blockedDates.push(dateStr);
                }
              } else if (blocked.blocked_date) {
                // Обратная совместимость: если есть только blocked_date
                blockedDates.push(blocked.blocked_date);
              }
            }
          });
        }
        
        // Также получаем Airbnb заблокированные даты
        if (blockedResult.data?.airbnb_blocked_dates) {
          blockedDates = [...blockedDates, ...blockedResult.data.airbnb_blocked_dates];
        }
          }
        } catch (jsonError) {
          console.error('Failed to parse blocked dates JSON:', jsonError);
          // Продолжаем без заблокированных дат
        }
      } else {
        const text = await blockedResponse.text();
        console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
        // Продолжаем без заблокированных дат
      }
    }
    
    // Убираем дубликаты
    blockedDates = [...new Set(blockedDates)];
    
    // Get massage bookings - загружаем всегда для отображения в календаре
    // Если выбрана вкладка "All Bookings" или "Massage", показываем все бронирования массажа
    // Если выбрана конкретная комната, не показываем бронирования массажа
    let massageBookings = [];
    if (!selectedRoom || isMassageTab) {
      try {
        console.log('Loading massage bookings for calendar...', { selectedRoom, isMassageTab });
        const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
        const massageResponse = await fetch('api.php?' + massageParams.toString(), {
          method: 'GET'
        });
        
        console.log('Massage bookings response status:', massageResponse.status);
        
        if (massageResponse.ok) {
          const massageContentType = massageResponse.headers.get('content-type');
          console.log('Massage bookings content-type:', massageContentType);
          
          if (massageContentType && massageContentType.includes('application/json')) {
            try {
              const massageResult = await massageResponse.json();
              console.log('Massage bookings result:', massageResult);
              
              if (massageResult.success && massageResult.data?.bookings) {
                const allMassageBookings = massageResult.data.bookings;
                console.log('All massage bookings before filter:', allMassageBookings.length, allMassageBookings);
                console.log('Massage booking statuses:', allMassageBookings.map(b => ({ id: b.id, status: b.status })));
                
                // Фильтруем бронирования массажа для календаря - показываем все кроме cancelled
                // (pending, confirmed, completed - все показываем в календаре)
                massageBookings = allMassageBookings.filter(booking => {
                  const isNotCancelled = booking.status !== 'cancelled';
                  console.log(`Massage booking ${booking.id}: status=${booking.status}, isNotCancelled=${isNotCancelled}`);
                  return isNotCancelled;
                });
                console.log('Filtered massage bookings for calendar:', massageBookings.length, massageBookings);
              }
            } catch (jsonError) {
              console.error('Failed to parse massage bookings JSON:', jsonError);
            }
          } else {
            const text = await massageResponse.text();
            console.error('API returned non-JSON response for massage bookings:', text.substring(0, 200));
          }
        } else {
          const errorText = await massageResponse.text();
          console.warn('Failed to fetch massage bookings:', massageResponse.status, errorText.substring(0, 200));
        }
      } catch (massageError) {
        console.error('Failed to fetch massage bookings:', massageError);
      }
    }
    
    if (loadingEl) loadingEl.style.display = 'none';
    if (gridEl) {
      gridEl.style.display = 'grid';
      renderAdminCalendar(gridEl, bookings, blockedDates, massageBookings);
    }
  } catch (error) {
    console.error('Load calendar error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    showStatus('Failed to load calendar: ' + error.message, 'error');
    // Показываем пустой календарь вместо полного сбоя
    if (gridEl) {
      gridEl.style.display = 'grid';
      // Рендерим календарь с пустыми данными, чтобы интерфейс был виден
      renderAdminCalendar(gridEl, [], [], []);
    }
  }
}

// Render admin calendar
let calendarStartMonth = 0; // Смещение для навигации по месяцам

function renderAdminCalendar(container, bookings, blockedDates, massageBookings = []) {
  console.log('renderAdminCalendar called with:', {
    bookingsCount: bookings.length,
    blockedDatesCount: blockedDates.length,
    massageBookingsCount: massageBookings.length,
    sampleBooking: bookings[0],
    sampleMassageBooking: massageBookings[0],
    sampleBlockedDate: blockedDates[0]
  });
  
  container.innerHTML = '';
  container.className = 'admin-calendar-grid';
  
  const today = new Date();
  const months = [];
  // Показываем 3 месяца, начиная с текущего + смещение
  for (let i = 0; i < 3; i++) {
    const date = new Date(today.getFullYear(), today.getMonth() + calendarStartMonth + i, 1);
    months.push(date);
  }
  
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  
  months.forEach((monthDate, monthIndex) => {
    const monthYear = monthDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();
    
    // Month header (visual separation between months)
    if (monthIndex > 0) {
      // Add empty row for spacing before month header (except for first month)
      for (let i = 0; i < 7; i++) {
        const spacer = document.createElement('div');
        spacer.className = 'admin-calendar-spacer';
        container.appendChild(spacer);
      }
    }
    
    const monthHeader = document.createElement('div');
    monthHeader.className = 'admin-calendar-month-header';
    monthHeader.textContent = monthYear;
    container.appendChild(monthHeader);
    
    // Day headers (only for first month, or for each month if you want them repeated)
    if (monthIndex === 0) {
      // Day headers only once at the top
      weekdays.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'admin-calendar-day-header';
        dayHeader.textContent = day;
        container.appendChild(dayHeader);
      });
    }
    
    // Empty cells for days before first day of month (keep them visible but empty for grid continuity)
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'admin-calendar-day';
      emptyCell.style.visibility = 'hidden';
      emptyCell.style.pointerEvents = 'none';
      container.appendChild(emptyCell);
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      const dateString = formatDateString(date);
      
      const dayCell = document.createElement('div');
      dayCell.className = 'admin-calendar-day';
      dayCell.textContent = day;
      
      const todayDate = new Date();
      todayDate.setHours(0, 0, 0, 0);
      const cellDate = new Date(date);
      cellDate.setHours(0, 0, 0, 0);
      
      // Проверяем тип бронирования для этой даты
      const bookingInfo = getBookingInfoForDate(dateString, bookings);
      const massageInfo = getMassageBookingInfoForDate(dateString, massageBookings);
      const isPast = cellDate < todayDate;
      
      // Логирование для первых нескольких дней для диагностики
      if (day <= 3 && monthIndex === 0) {
        console.log(`Date ${dateString}:`, {
          bookingInfo: bookingInfo ? { id: bookingInfo.id, checkin: bookingInfo.checkin_date, checkout: bookingInfo.checkout_date } : null,
          massageInfo: massageInfo ? { id: massageInfo.id, date: massageInfo.massage_date } : null,
          isBlocked: blockedDates.includes(dateString)
        });
      }
      
      if (blockedDates.includes(dateString)) {
        dayCell.classList.add('blocked');
        if (isPast) {
          dayCell.classList.add('past');
        }
      } else if (massageInfo) {
        // Есть бронирование массажа - розовый цвет
        dayCell.classList.add('massage');
        // Если pending, добавляем класс pending для визуального отличия
        if (massageInfo.status === 'pending') {
          dayCell.classList.add('pending');
        }
        if (isPast) {
          dayCell.classList.add('past');
        }
        dayCell.classList.add('has-booking');
      } else if (bookingInfo) {
        // Есть бронирование комнаты - определяем тип
        if (bookingInfo.payment_status === 'paid') {
          dayCell.classList.add('paid');
          // Для прошедших дат с оплаченными бронированиями - более тусклый зеленый
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else if (bookingInfo.status === 'confirmed') {
          dayCell.classList.add('confirmed');
          // Для прошедших дат с одобренными бронированиями - более тусклый желтый
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else if (bookingInfo.status === 'pending') {
          // Pending бронирования - оранжевый цвет
          dayCell.classList.add('pending');
          if (isPast) {
            dayCell.classList.add('past');
          }
        } else {
          dayCell.classList.add('booked');
          if (isPast) {
            dayCell.classList.add('past');
          }
        }
        dayCell.classList.add('has-booking');
      } else {
        // Нет бронирования - доступная дата
        dayCell.classList.add('available');
        if (isPast) {
          dayCell.classList.add('past');
        }
      }
      
      container.appendChild(dayCell);
    }
  });
}

// Flash field red twice (for validation)
function flashFieldTwice(field) {
  if (!field) return;
  
  // Helper function to flash once
  const flashOnce = (el) => {
    if (!el) return;
    el.classList.remove('flash-invalid');
    void el.offsetWidth; // restart animation
    el.classList.add('flash-invalid');
    setTimeout(() => {
      try {
        el.classList.remove('flash-invalid');
      } catch (_) {}
    }, 350);
  };
  
  // Flash first time
  flashOnce(field);
  
  // Flash second time after first animation completes
  setTimeout(() => {
    flashOnce(field);
  }, 700);
}

// Block date
async function blockDate() {
  const roomSelect = document.getElementById('block-room-select');
  const dateFromInput = document.getElementById('block-date-from');
  const dateToInput = document.getElementById('block-date-to');
  const reasonInput = document.getElementById('block-reason');
  
  if (!roomSelect || !dateFromInput || !dateToInput) {
    showStatus('Room and date inputs not found', 'error');
    return;
  }
  
  const roomName = roomSelect.value;
  const dateFrom = dateFromInput.value;
  const dateTo = dateToInput.value;
  const reason = reasonInput ? reasonInput.value : '';
  
  // Validate required fields and flash them red twice if empty
  let hasErrors = false;
  
  if (!roomName) {
    flashFieldTwice(roomSelect);
    hasErrors = true;
  }
  
  if (!dateFrom) {
    // Check if Flatpickr is used for date input
    let visibleDateFrom = dateFromInput;
    if (typeof flatpickr !== 'undefined' && dateFromInput._flatpickr) {
      const fpInstance = dateFromInput._flatpickr;
      if (fpInstance.altInput) {
        visibleDateFrom = fpInstance.altInput;
      }
    }
    flashFieldTwice(visibleDateFrom);
    flashFieldTwice(dateFromInput);
    hasErrors = true;
  }
  
  if (!dateTo) {
    // Check if Flatpickr is used for date input
    let visibleDateTo = dateToInput;
    if (typeof flatpickr !== 'undefined' && dateToInput._flatpickr) {
      const fpInstance = dateToInput._flatpickr;
      if (fpInstance.altInput) {
        visibleDateTo = fpInstance.altInput;
      }
    }
    flashFieldTwice(visibleDateTo);
    flashFieldTwice(dateToInput);
    hasErrors = true;
  }
  
  if (hasErrors) {
    showStatus('Please fill in all required fields', 'error');
    return;
  }
  
  if (new Date(dateFrom) > new Date(dateTo)) {
    showStatus('Date From must be before Date To', 'error');
    return;
  }
  
  try {
    // If "For all bookings" is selected, create a single record with room_name = "__all__"
    if (roomName === '__all__') {
      const formData = new FormData();
      formData.append('action', 'block_date');
      formData.append('room_name', '__all__');
      formData.append('date_from', dateFrom);
      formData.append('date_to', dateTo);
      if (reason) {
        formData.append('reason', reason);
      }
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to block date period`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        showStatus('Date period blocked successfully for all bookings!');
        // Ensure we stay on Calendar Management section
        switchPrimarySection('bookings');
        showSection('calendar');
        // Reload calendar and blocked dates list
        loadCalendarData();
        loadBlockedDates();
        
        // Clear form
        roomSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        if (reasonInput) {
          reasonInput.value = '';
        }
      } else {
        showStatus(result.error || 'Failed to block date period for all bookings', 'error');
      }
    } else {
      // Block for single booking
      const formData = new FormData();
      formData.append('action', 'block_date');
      formData.append('room_name', roomName);
      formData.append('date_from', dateFrom);
      formData.append('date_to', dateTo);
      if (reason) {
        formData.append('reason', reason);
      }
      
      const response = await fetch('api.php', {
        method: 'POST',
        body: formData
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to block date period`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        showStatus('Date period blocked successfully!');
        // Ensure we stay on Calendar Management section
        switchPrimarySection('bookings');
        showSection('calendar');
        // Reload calendar and blocked dates list
        loadCalendarData();
        loadBlockedDates();
        
        // Clear form
        roomSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        if (reasonInput) {
          reasonInput.value = '';
        }
      } else {
        showStatus(result.error || 'Failed to block date period', 'error');
      }
    }
  } catch (error) {
    console.error('Block date error:', error);
    showStatus('Failed to block date period: ' + error.message, 'error');
  }
}

// Unblock date period
async function unblockDate(blockedDateId) {
  if (!confirm('Are you sure you want to unblock this date period?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'unblock_date');
    formData.append('block_id', blockedDateId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to unblock date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date unblocked successfully!');
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to unblock date');
    }
  } catch (error) {
    console.error('Unblock date error:', error);
    showStatus('Failed to unblock date: ' + error.message, 'error');
  }
}

// Load blocked dates (periods)
async function loadBlockedDates() {
  const listEl = document.getElementById('blocked-dates-list');
  if (!listEl) return;
  
  try {
    const params = new URLSearchParams({ action: 'get_blocked_dates' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load blocked dates');
    }
    
    // Проверяем, что ответ действительно JSON, а не HTML (ошибка PHP)
    const contentType = response.headers.get('content-type');
    let result = null;
    
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
      listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates: API returned invalid response</p>';
      return;
    }
    
    try {
      result = await response.json();
    } catch (jsonError) {
      console.error('Failed to parse blocked dates JSON:', jsonError);
      listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates: Invalid JSON response</p>';
      return;
    }
    
    listEl.innerHTML = '';
    
    if (result && result.success && result.data?.blocked_dates && result.data.blocked_dates.length > 0) {
      result.data.blocked_dates.forEach(blocked => {
        // Используем date_from/date_to если есть, иначе blocked_date для обратной совместимости
        const dateFrom = blocked.date_from || blocked.blocked_date || '';
        const dateTo = blocked.date_to || blocked.blocked_date || '';
        const reason = blocked.reason || '';
        
        // Отображаем "For all bookings" вместо "__all__"
        const displayRoomName = blocked.room_name === '__all__' ? 'For all bookings' : (blocked.room_name || '—');
        
        const item = document.createElement('div');
        item.className = 'blocked-date-item';
        item.innerHTML = `
          <div>
            <strong style="color: #000;">${escapeHtml(displayRoomName)}</strong>
            <div style="font-size: 14px; color: #718096; margin-top: 4px;">
              ${formatDate(dateFrom)} — ${formatDate(dateTo)}
              ${reason ? ` — ${escapeHtml(reason)}` : ''}
            </div>
          </div>
          <button class="admin-btn admin-btn-danger" onclick="unblockDate(${blocked.id})">Unblock</button>
        `;
        listEl.appendChild(item);
      });
    } else {
      listEl.innerHTML = '<p style="color: #718096; text-align: center; padding: 20px;">No blocked dates</p>';
    }
  } catch (error) {
    console.error('Load blocked dates error:', error);
    listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates</p>';
  }
}

// Initialize calendar blocking
function initCalendarBlocking() {
  const blockBtn = document.getElementById('block-date-btn');
  const refreshBtn = document.getElementById('calendar-refresh');
  const syncBtn = document.getElementById('airbnb-sync-btn');
  
  // Initialize Flatpickr for date inputs (same approach as room pages)
  const dateFromInput = document.getElementById('block-date-from');
  const dateToInput = document.getElementById('block-date-to');
  
  if (dateFromInput && typeof flatpickr !== 'undefined' && !dateFromInput.dataset.flatpickrInitialized) {
    // Mark as enhanced BEFORE Flatpickr initialization to prevent enhanceDateInputs from processing it
    dateFromInput.dataset.enhancedDate = '1';
    
    // Удаляем display proxy inputs от enhanceDateInputs, если они есть (чтобы избежать дублирования)
    const dateFromDisplay = dateFromInput.previousElementSibling && dateFromInput.previousElementSibling.tagName === 'INPUT' && dateFromInput.previousElementSibling.readOnly ? dateFromInput.previousElementSibling : null;
    if (dateFromDisplay) {
      dateFromDisplay.remove();
    }
    
    // Восстанавливаем нормальное состояние input для Flatpickr
    // Flatpickr сам стилизует input, поэтому его нужно оставить видимым
    dateFromInput.style.position = '';
    dateFromInput.style.opacity = '';
    dateFromInput.style.pointerEvents = '';
    dateFromInput.style.width = '';
    dateFromInput.style.height = '';
    dateFromInput.style.margin = '';
    dateFromInput.style.visibility = '';
    dateFromInput.style.clip = '';
    dateFromInput.removeAttribute('readonly');
    
    // Убеждаемся, что тип input остается "date" для HTML5 валидации
    dateFromInput.type = 'date';
    
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    const fpFrom = flatpickr(dateFromInput, {
      dateFormat: 'Y-m-d',
      allowInput: false, // Отключаем прямой ввод в input
      clickOpens: true, // Открываем календарь при клике
      altInput: true, // Используем альтернативный input для отображения с placeholder
      altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
      placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
      minDate: minDate, // Minimum date (3 years ago)
      maxDate: maxDate, // Maximum date (3 years ahead)
      monthSelectorType: 'static', // Выпадающий список для месяцев
      yearSelectorType: 'static', // Выпадающий список для годов
      onReady: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = instance.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Скрываем оригинальный input, так как используем altInput для отображения
        // Убираем его далеко в сторону, чтобы он не перехватывал клики
        if (instance.input) {
          instance.input.style.position = 'absolute';
          instance.input.style.opacity = '0';
          instance.input.style.width = '0';
          instance.input.style.height = '0';
          instance.input.style.padding = '0';
          instance.input.style.margin = '0';
          instance.input.style.border = 'none';
          instance.input.style.pointerEvents = 'none';
          instance.input.style.left = '-9999px';
          instance.input.style.top = '-9999px';
          instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
        }
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        if (instance.altInput) {
          instance.altInput.style.width = '100%';
          instance.altInput.style.cursor = 'pointer';
          // Устанавливаем placeholder после полной инициализации
          if (!instance.altInput.value) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
          }
        }
        
        // Применяем стили к стрелкам навигации
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
      },
      onOpen: function(selectedDates, dateStr, instance) {
        // Применяем стили к стрелкам каждый раз, когда календарь открывается
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
        
        // Отслеживаем появление выпадающего списка года
        const observer = new MutationObserver(() => {
          applyFlatpickrArrowStyles(instance);
        });
        
        if (instance.calendarContainer) {
          observer.observe(instance.calendarContainer, {
            childList: true,
            subtree: true
          });
          
          // Останавливаем наблюдение через 2 секунды
          setTimeout(() => {
            observer.disconnect();
          }, 2000);
        }
      },
      onChange: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что значение реального input обновлено
        if (dateStr) {
          dateFromInput.value = dateStr;
          // Убеждаемся, что тип остается "date"
          dateFromInput.type = 'date';
          // Вызываем события для валидации формы
          dateFromInput.dispatchEvent(new Event('input', { bubbles: true }));
          dateFromInput.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          dateFromInput.value = '';
          // Восстанавливаем placeholder если значение пустое
          if (instance.altInput) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
            instance.altInput.value = '';
          }
          dateFromInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    
    dateFromInput.dataset.flatpickrInitialized = '1';
    
    // Скрываем оригинальный input сразу после инициализации
    setTimeout(() => {
      if (fpFrom.input && fpFrom.altInput) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = fpFrom.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
        fpFrom.input.style.position = 'absolute';
        fpFrom.input.style.opacity = '0';
        fpFrom.input.style.width = '0';
        fpFrom.input.style.height = '0';
        fpFrom.input.style.padding = '0';
        fpFrom.input.style.margin = '0';
        fpFrom.input.style.border = 'none';
        fpFrom.input.style.pointerEvents = 'none';
        fpFrom.input.style.left = '-9999px';
        fpFrom.input.style.top = '-9999px';
        fpFrom.input.style.visibility = 'visible';
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        fpFrom.altInput.style.width = '100%';
        fpFrom.altInput.style.cursor = 'pointer';
        
        // Убеждаемся, что placeholder установлен в altInput после инициализации
        if (!fpFrom.altInput.value) {
          fpFrom.altInput.placeholder = 'dd.mm.yyyy';
        }
      }
    }, 50);
  }
  
  if (dateToInput && typeof flatpickr !== 'undefined' && !dateToInput.dataset.flatpickrInitialized) {
    // Mark as enhanced BEFORE Flatpickr initialization to prevent enhanceDateInputs from processing it
    dateToInput.dataset.enhancedDate = '1';
    
    // Удаляем display proxy inputs от enhanceDateInputs, если они есть (чтобы избежать дублирования)
    const dateToDisplay = dateToInput.previousElementSibling && dateToInput.previousElementSibling.tagName === 'INPUT' && dateToInput.previousElementSibling.readOnly ? dateToInput.previousElementSibling : null;
    if (dateToDisplay) {
      dateToDisplay.remove();
    }
    
    // Восстанавливаем нормальное состояние input для Flatpickr
    // Flatpickr сам стилизует input, поэтому его нужно оставить видимым
    dateToInput.style.position = '';
    dateToInput.style.opacity = '';
    dateToInput.style.pointerEvents = '';
    dateToInput.style.width = '';
    dateToInput.style.height = '';
    dateToInput.style.margin = '';
    dateToInput.style.visibility = '';
    dateToInput.style.clip = '';
    dateToInput.removeAttribute('readonly');
    
    // Убеждаемся, что тип input остается "date" для HTML5 валидации
    dateToInput.type = 'date';
    
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    const fpTo = flatpickr(dateToInput, {
      dateFormat: 'Y-m-d',
      allowInput: false, // Отключаем прямой ввод в input
      clickOpens: true, // Открываем календарь при клике
      altInput: true, // Используем альтернативный input для отображения с placeholder
      altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
      placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
      minDate: minDate, // Minimum date (3 years ago)
      maxDate: maxDate, // Maximum date (3 years ahead)
      monthSelectorType: 'static', // Выпадающий список для месяцев
      yearSelectorType: 'static', // Выпадающий список для годов
      onReady: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = instance.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Скрываем оригинальный input, так как используем altInput для отображения
        // Убираем его далеко в сторону, чтобы он не перехватывал клики
        if (instance.input) {
          instance.input.style.position = 'absolute';
          instance.input.style.opacity = '0';
          instance.input.style.width = '0';
          instance.input.style.height = '0';
          instance.input.style.padding = '0';
          instance.input.style.margin = '0';
          instance.input.style.border = 'none';
          instance.input.style.pointerEvents = 'none';
          instance.input.style.left = '-9999px';
          instance.input.style.top = '-9999px';
          instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
        }
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        if (instance.altInput) {
          instance.altInput.style.width = '100%';
          instance.altInput.style.cursor = 'pointer';
          // Устанавливаем placeholder после полной инициализации
          if (!instance.altInput.value) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
          }
        }
        
        // Применяем стили к стрелкам навигации
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
      },
      onOpen: function(selectedDates, dateStr, instance) {
        // Применяем стили к стрелкам каждый раз, когда календарь открывается
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 50);
        setTimeout(() => {
          applyFlatpickrArrowStyles(instance);
        }, 200);
        
        // Отслеживаем появление выпадающего списка года
        const observer = new MutationObserver(() => {
          applyFlatpickrArrowStyles(instance);
        });
        
        if (instance.calendarContainer) {
          observer.observe(instance.calendarContainer, {
            childList: true,
            subtree: true
          });
          
          // Останавливаем наблюдение через 2 секунды
          setTimeout(() => {
            observer.disconnect();
          }, 2000);
        }
      },
      onChange: function(selectedDates, dateStr, instance) {
        // Убеждаемся, что значение реального input обновлено
        if (dateStr) {
          dateToInput.value = dateStr;
          // Убеждаемся, что тип остается "date"
          dateToInput.type = 'date';
          // Вызываем события для валидации формы
          dateToInput.dispatchEvent(new Event('input', { bubbles: true }));
          dateToInput.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          dateToInput.value = '';
          // Восстанавливаем placeholder если значение пустое
          if (instance.altInput) {
            instance.altInput.placeholder = 'dd.mm.yyyy';
            instance.altInput.value = '';
          }
          dateToInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    
    dateToInput.dataset.flatpickrInitialized = '1';
    
    // Скрываем оригинальный input сразу после инициализации
    setTimeout(() => {
      if (fpTo.input && fpTo.altInput) {
        // Убеждаемся, что родительский контейнер имеет position: relative
        const parent = fpTo.input.parentElement;
        if (parent && window.getComputedStyle(parent).position === 'static') {
          parent.style.position = 'relative';
        }
        
        // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
        fpTo.input.style.position = 'absolute';
        fpTo.input.style.opacity = '0';
        fpTo.input.style.width = '0';
        fpTo.input.style.height = '0';
        fpTo.input.style.padding = '0';
        fpTo.input.style.margin = '0';
        fpTo.input.style.border = 'none';
        fpTo.input.style.pointerEvents = 'none';
        fpTo.input.style.left = '-9999px';
        fpTo.input.style.top = '-9999px';
        fpTo.input.style.visibility = 'visible';
        
        // Убеждаемся, что altInput имеет правильный размер и кликабельную область
        fpTo.altInput.style.width = '100%';
        fpTo.altInput.style.cursor = 'pointer';
        
        // Убеждаемся, что placeholder установлен в altInput после инициализации
        if (!fpTo.altInput.value) {
          fpTo.altInput.placeholder = 'dd.mm.yyyy';
        }
      }
    }, 50);
  }
  
  // Инициализация вкладок комнат для календаря
  const roomTabs = document.querySelectorAll('.calendar-room-tab');
  roomTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      // Убираем активный класс со всех вкладок
      roomTabs.forEach(t => t.classList.remove('active'));
      // Добавляем активный класс к выбранной вкладке
      tab.classList.add('active');
      // Загружаем календарь для выбранной комнаты
      loadCalendarData();
    });
  });
  
  if (blockBtn) {
    blockBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      blockDate();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadCalendarData();
    });
  }
  
  // Навигация по календарю
  const prevBtn = document.getElementById('calendar-prev');
  const nextBtn = document.getElementById('calendar-next');
  
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      calendarStartMonth -= 3;
      loadCalendarData();
    });
  }
  
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      calendarStartMonth += 3;
      loadCalendarData();
    });
  }
  
  if (syncBtn) {
    syncBtn.addEventListener('click', syncAirbnbCalendar);
  }
  
  // Загружаем статус синхронизации при открытии раздела
  loadAirbnbSyncStatus();
}

// Синхронизация календаря Airbnb
async function syncAirbnbCalendar() {
  const syncBtn = document.getElementById('airbnb-sync-btn');
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!syncBtn || !statusEl || !statusTextEl) {
    return;
  }
  
  // Показываем состояние загрузки
  syncBtn.disabled = true;
  syncBtn.textContent = 'Syncing...';
  statusEl.style.display = 'block';
  statusTextEl.textContent = 'Syncing with Airbnb calendar...';
  statusTextEl.style.color = '#4299e1';
  
  try {
    const formData = new FormData();
    formData.append('action', 'sync_airbnb');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to sync Airbnb calendar');
    }
    
    const result = await response.json();
    
    if (result.success) {
      statusTextEl.textContent = `Sync completed successfully! ${result.data?.synced_rooms?.length || 0} room(s) synced.`;
      statusTextEl.style.color = '#2f855a';
      
      // Если были ошибки, показываем их
      if (result.data?.errors && result.data.errors.length > 0) {
        const errors = result.data.errors.map(e => `${e.room}: ${e.error}`).join(', ');
        statusTextEl.textContent += ` Errors: ${errors}`;
        statusTextEl.style.color = '#e53e3e';
      }
      
      // Обновляем календарь после синхронизации
      setTimeout(() => {
        loadCalendarData();
        loadAirbnbSyncStatus();
      }, 1000);
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    console.error('Sync Airbnb error:', error);
    statusTextEl.textContent = 'Failed to sync Airbnb calendar: ' + error.message;
    statusTextEl.style.color = '#e53e3e';
  } finally {
    syncBtn.disabled = false;
    syncBtn.textContent = 'Sync with Airbnb';
  }
}

// Загрузка статуса синхронизации Airbnb
async function loadAirbnbSyncStatus() {
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!statusEl || !statusTextEl) {
    return;
  }
  
  try {
    const params = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      if (result.success && result.data?.sync_status && result.data.sync_status.length > 0) {
        const status = result.data.sync_status;
        const statusText = status.map(s => {
          const lastSync = s.last_synced ? new Date(s.last_synced).toLocaleString() : 'Never';
          return `${s.room_name}: ${s.blocked_count} blocked dates, last synced: ${lastSync}`;
        }).join('<br>');
        
        // Last Sync Status display removed per user request
        // statusTextEl.innerHTML = `<strong>Last Sync Status:</strong><br>${statusText}`;
        statusTextEl.style.color = '#4a5568';
        statusEl.style.display = 'block';
      } else {
        statusTextEl.textContent = 'No sync status available. Click "Sync with Airbnb" to start syncing.';
        statusTextEl.style.color = '#718096';
        statusEl.style.display = 'block';
      }
    }
  } catch (error) {
    console.error('Load sync status error:', error);
    // Не показываем ошибку, просто скрываем статус
    statusEl.style.display = 'none';
  }
}

// ==========================================
// DASHBOARD STATS
// ==========================================

// Update dashboard statistics
// Initialize dashboard date filters
function initDashboardFilters(dashboardType) {
  const dateFromId = `${dashboardType}-dashboard-date-from`;
  const dateToId = `${dashboardType}-dashboard-date-to`;
  const resetId = `${dashboardType}-dashboard-reset-filter`;
  
  const dateFromInput = document.getElementById(dateFromId);
  const dateToInput = document.getElementById(dateToId);
  const resetBtn = document.getElementById(resetId);
  
  // Initialize Flatpickr for date filters
  if (typeof flatpickr !== 'undefined') {
    // Calculate date range: current year ± 3 years
    const currentYear = new Date().getFullYear();
    const minYear = currentYear - 3;
    const maxYear = currentYear + 3;
    const minDate = new Date(minYear, 0, 1); // January 1st of minYear
    const maxDate = new Date(maxYear, 11, 31); // December 31st of maxYear
    
    if (dateFromInput && !dateFromInput._flatpickr) {
      flatpickr(dateFromInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        altInput: true,
        altFormat: 'F j, Y',
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
    
    if (dateToInput && !dateToInput._flatpickr) {
      flatpickr(dateToInput, {
        dateFormat: 'Y-m-d',
        allowInput: true,
        clickOpens: true,
        altInput: true,
        altFormat: 'F j, Y',
        minDate: minDate, // Minimum date (3 years ago)
        maxDate: maxDate, // Maximum date (3 years ahead)
        monthSelectorType: 'static', // Выпадающий список для месяцев
        yearSelectorType: 'static' // Выпадающий список для годов
      });
    }
  }
  
  // Add event listeners for date changes
  if (dateFromInput) {
    dateFromInput.addEventListener('change', () => {
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
  
  if (dateToInput) {
    dateToInput.addEventListener('change', () => {
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
  
  // Reset button
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (dateFromInput) {
        if (dateFromInput._flatpickr) {
          dateFromInput._flatpickr.clear();
        } else {
          dateFromInput.value = '';
        }
      }
      if (dateToInput) {
        if (dateToInput._flatpickr) {
          dateToInput._flatpickr.clear();
        } else {
          dateToInput.value = '';
        }
      }
      
      if (dashboardType === 'bookings') {
        updateBookingsDashboardStats();
      } else if (dashboardType === 'content') {
        updateContentDashboardStats();
      } else if (dashboardType === 'accounts') {
        updateAccountsDashboardStats();
      }
    });
  }
}

// Get dashboard date filter values
function getDashboardDateFilters(dashboardType) {
  const dateFromId = `${dashboardType}-dashboard-date-from`;
  const dateToId = `${dashboardType}-dashboard-date-to`;
  
  const dateFromInput = document.getElementById(dateFromId);
  const dateToInput = document.getElementById(dateToId);
  
  let dateFrom = null;
  let dateTo = null;
  
  if (dateFromInput) {
    if (dateFromInput._flatpickr && dateFromInput._flatpickr.selectedDates.length > 0) {
      dateFrom = dateFromInput._flatpickr.formatDate(dateFromInput._flatpickr.selectedDates[0], 'Y-m-d');
    } else if (dateFromInput.value) {
      dateFrom = dateFromInput.value;
    }
  }
  
  if (dateToInput) {
    if (dateToInput._flatpickr && dateToInput._flatpickr.selectedDates.length > 0) {
      dateTo = dateToInput._flatpickr.formatDate(dateToInput._flatpickr.selectedDates[0], 'Y-m-d');
    } else if (dateToInput.value) {
      dateTo = dateToInput.value;
    }
  }
  
  return { dateFrom, dateTo };
}

// Filter bookings by date range
function filterBookingsByDate(bookings, dateFrom, dateTo) {
  if (!dateFrom && !dateTo) {
    return bookings; // No filter, return all
  }
  
  return bookings.filter(booking => {
    let bookingDate = null;
    
    // For room bookings, use checkin_date
    if (booking.booking_type === 'room' && booking.checkin_date) {
      bookingDate = booking.checkin_date;
    }
    // For massage bookings, use massage_date
    else if (booking.booking_type === 'massage' && booking.massage_date) {
      bookingDate = booking.massage_date;
    }
    // Fallback to created_at
    else if (booking.created_at) {
      bookingDate = booking.created_at.split(' ')[0]; // Extract date part
    }
    
    if (!bookingDate) {
      return false; // No date available, exclude
    }
    
    // Check if booking date is within range
    if (dateFrom && bookingDate < dateFrom) {
      return false;
    }
    if (dateTo && bookingDate > dateTo) {
      return false;
    }
    
    return true;
  });
}

// Update Bookings Management Dashboard statistics
async function updateBookingsDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('bookings');
    
    // Load room bookings
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    let allBookings = [];
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        allBookings = result.data.bookings.map(booking => ({
          ...booking,
          booking_type: 'room'
        }));
      }
    }
    
    // Load massage bookings
    try {
      const massageParams = new URLSearchParams({ action: 'get_massage_bookings' });
      if (dateFrom) massageParams.append('date_from', dateFrom);
      if (dateTo) massageParams.append('date_to', dateTo);
      
      const massageResponse = await fetch('api.php?' + massageParams.toString(), {
        method: 'GET'
      });
      
      if (massageResponse.ok) {
        const massageContentType = massageResponse.headers.get('content-type');
        if (massageContentType && massageContentType.includes('application/json')) {
          try {
            const massageResult = await massageResponse.json();
            if (massageResult.success && massageResult.data?.bookings) {
              const massageBookings = massageResult.data.bookings.map(booking => ({
                ...booking,
                booking_type: 'massage'
              }));
              allBookings = [...allBookings, ...massageBookings];
            }
          } catch (jsonError) {
            console.error('Failed to parse massage bookings JSON in dashboard:', jsonError);
          }
        }
      }
    } catch (massageError) {
      console.warn('Failed to load massage bookings for dashboard:', massageError);
    }
    
    // Apply date filter if needed (client-side fallback)
    allBookings = filterBookingsByDate(allBookings, dateFrom, dateTo);
    
    // Calculate statistics for all bookings (rooms + massage)
    const pending = allBookings.filter(b => b.status === 'pending').length;
    const confirmed = allBookings.filter(b => {
      if (b.booking_type === 'massage') {
        return b.status === 'confirmed';
      } else {
        return b.status === 'confirmed' && (!b.payment_status || b.payment_status !== 'paid');
      }
    }).length;
    const cancelled = allBookings.filter(b => b.status === 'cancelled').length;
    const total = allBookings.length;
    
    // Update UI
    const pendingEl = document.getElementById('pending-bookings');
    const totalEl = document.getElementById('total-bookings');
    const confirmedEl = document.getElementById('confirmed-bookings');
    const cancelledEl = document.getElementById('cancelled-bookings');
    
    if (pendingEl) pendingEl.textContent = pending;
    if (totalEl) totalEl.textContent = total;
    if (confirmedEl) confirmedEl.textContent = confirmed;
    if (cancelledEl) cancelledEl.textContent = cancelled;
  } catch (error) {
    console.error('Error updating bookings dashboard stats:', error);
  }
}

// Update Content Management Dashboard statistics
async function updateContentDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('content');
    
    // Update rooms count
    const roomsResponse = await fetch('api.php?action=get_rooms');
    if (roomsResponse.ok) {
      const roomsResult = await roomsResponse.json();
      if (roomsResult.success && roomsResult.data) {
        const totalRooms = roomsResult.data.length;
        const roomsEl = document.getElementById('total-rooms');
        if (roomsEl) roomsEl.textContent = totalRooms;
      }
    }
    
    // Update massage services count (if available)
    // Update yoga services count (if available)
    
    // Note: Content dashboard doesn't have date-based statistics yet
    // This is a placeholder for future date filtering if needed
    // Update last updated time
    const lastUpdatedEl = document.getElementById('last-updated');
    if (lastUpdatedEl) {
      lastUpdatedEl.textContent = new Date().toLocaleString();
    }
  } catch (error) {
    console.error('Error updating content dashboard stats:', error);
  }
}

// Update Account Management Dashboard statistics
async function updateAccountsDashboardStats() {
  try {
    // Get date filters
    const { dateFrom, dateTo } = getDashboardDateFilters('accounts');
    
    const formData = new FormData();
    formData.append('action', 'get_users');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        let users = result.data;
        
        // Ensure users is an array
        if (!Array.isArray(users)) {
          console.error('Users data is not an array:', users);
          users = [];
        }
        
        // Filter users by date range if filters are set
        if (dateFrom || dateTo) {
          users = users.filter(u => {
            if (!u.created_at) return false;
            const createdDate = u.created_at.split(' ')[0]; // Extract date part
            
            if (dateFrom && createdDate < dateFrom) return false;
            if (dateTo && createdDate > dateTo) return false;
            
            return true;
          });
        }
        
        // Calculate statistics
        const total = users.length;
        const verified = users.filter(u => u.is_verified).length;
        
        // New this month (only if no date filter is set, otherwise show filtered count)
        let newThisMonth = 0;
        if (!dateFrom && !dateTo) {
          const now = new Date();
          const currentMonth = now.getMonth();
          const currentYear = now.getFullYear();
          newThisMonth = users.filter(u => {
            const created = new Date(u.created_at);
            return created.getMonth() === currentMonth && created.getFullYear() === currentYear;
          }).length;
        } else {
          // If date filter is set, show count of users in filtered range
          newThisMonth = users.length;
        }
        
        // Active this week
        const weekAgo = new Date(new Date().getTime() - 7 * 24 * 60 * 60 * 1000);
        const activeThisWeek = users.filter(u => {
          if (!u.last_session) return false;
          const lastSession = new Date(u.last_session);
          return lastSession >= weekAgo;
        }).length;
        
        // Update UI
        const totalEl = document.getElementById('total-accounts');
        const verifiedEl = document.getElementById('verified-accounts');
        const newMonthEl = document.getElementById('new-accounts-month');
        const activeWeekEl = document.getElementById('active-accounts-week');
        
        if (totalEl) totalEl.textContent = total;
        if (verifiedEl) verifiedEl.textContent = verified;
        if (newMonthEl) newMonthEl.textContent = newThisMonth;
        if (activeWeekEl) activeWeekEl.textContent = activeThisWeek;
      }
    }
  } catch (error) {
    console.error('Error updating accounts dashboard stats:', error);
  }
}

// Legacy function for backward compatibility
async function updateDashboardStats() {
  try {
    const params = new URLSearchParams({ action: 'get_bookings' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        const bookings = result.data.bookings;
        const pendingCount = bookings.filter(b => b.status === 'pending').length;
        const totalCount = bookings.length;
        
        const pendingEl = document.getElementById('pending-bookings');
        const totalEl = document.getElementById('total-bookings');
        
        if (pendingEl) pendingEl.textContent = pendingCount;
        if (totalEl) totalEl.textContent = totalCount;
      }
    }
  } catch (error) {
    console.error('Update dashboard stats error:', error);
  }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString + 'T00:00:00');
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    return `${months[date.getMonth()]} ${String(date.getDate()).padStart(2, '0')}, ${date.getFullYear()}`;
  } catch (e) {
    return dateString;
  }
}

function formatDateTime(dateString) {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return dateString;
  }
}

function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// parseLocalDate - парсинг даты YYYY-MM-DD как локальной даты (без часового пояса)
// Используется для избежания проблем с часовыми поясами
function parseLocalDate(iso) {
  if (!iso) return null;
  const parts = String(iso).split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10) - 1;
  const d = parseInt(parts[2], 10);
  if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
  return new Date(y, m, d);
}

function isDateBooked(dateString, bookings) {
  const checkDate = new Date(dateString + 'T00:00:00');
  
  return bookings.some(booking => {
    const checkin = new Date(booking.checkin_date + 'T00:00:00');
    const checkout = new Date(booking.checkout_date + 'T00:00:00');
    return checkDate >= checkin && checkDate < checkout;
  });
}

// Получить информацию о бронировании для конкретной даты
function getBookingInfoForDate(dateString, bookings) {
  if (!bookings || bookings.length === 0) {
    return null;
  }
  
  // Используем parseLocalDate для правильной обработки дат без часового пояса
  const checkDate = parseLocalDate(dateString);
  if (!checkDate) {
    console.warn('Invalid dateString in getBookingInfoForDate:', dateString);
    return null;
  }
  
  for (const booking of bookings) {
    if (!booking.checkin_date || !booking.checkout_date) {
      continue;
    }
    
    const checkin = parseLocalDate(booking.checkin_date);
    const checkout = parseLocalDate(booking.checkout_date);
    
    if (!checkin || !checkout) {
      console.warn('Invalid dates in booking:', booking.id, { checkin: booking.checkin_date, checkout: booking.checkout_date });
      continue;
    }
    
    // Проверяем, попадает ли дата в период бронирования (checkin <= date < checkout)
    if (checkDate >= checkin && checkDate < checkout) {
      return booking;
    }
  }
  
  return null;
}

function getMassageBookingInfoForDate(dateString, massageBookings) {
  for (const booking of massageBookings) {
    if (booking.massage_date === dateString) {
      return booking;
    }
  }
  
  return null;
}

// Account Management functions
let allAccounts = [];
let filteredAccounts = [];

// Load accounts data
async function loadAccountsData() {
  const loadingEl = document.getElementById('accounts-loading');
  const listEl = document.getElementById('accounts-list');
  const emptyEl = document.getElementById('accounts-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    const formData = new FormData();
    formData.append('action', 'get_users');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        allAccounts = result.data;
        filteredAccounts = [...allAccounts];
        renderAccountsList();
        populateYearFilter();
      } else {
        allAccounts = [];
        filteredAccounts = [];
        renderAccountsList();
      }
    } else {
      console.error('Failed to load accounts');
      allAccounts = [];
      filteredAccounts = [];
      renderAccountsList();
    }
  } catch (error) {
    console.error('Error loading accounts:', error);
    allAccounts = [];
    filteredAccounts = [];
    renderAccountsList();
  } finally {
    if (loadingEl) loadingEl.style.display = 'none';
  }
}

// Render accounts list
function renderAccountsList() {
  const listEl = document.getElementById('accounts-list');
  const emptyEl = document.getElementById('accounts-empty');
  
  if (!listEl || !emptyEl) return;
  
  if (filteredAccounts.length === 0) {
    listEl.style.display = 'none';
    emptyEl.style.display = 'block';
    return;
  }
  
  listEl.style.display = 'block';
  emptyEl.style.display = 'none';
  
  listEl.innerHTML = filteredAccounts.map(user => {
    const createdDate = new Date(user.created_at);
    const formattedDate = createdDate.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
    
    const lastSession = user.last_session ? new Date(user.last_session).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    }) : 'Never';
    
    return `
      <div class="user-card" data-user-id="${user.id}">
        <div class="user-card-header">
          <h3>${escapeHtml(user.name)}</h3>
          <button class="admin-btn admin-btn-danger" onclick="deleteUser(${user.id})">Delete</button>
        </div>
        <div class="user-details-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <span class="user-detail-value">${escapeHtml(user.email)}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Phone</span>
            <span class="user-detail-value">${escapeHtml(user.phone || 'N/A')}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Additional Phone</span>
            <span class="user-detail-value">${escapeHtml(user.phone2 || 'N/A')}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Registered</span>
            <span class="user-detail-value">${formattedDate}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Last Session</span>
            <span class="user-detail-value">${lastSession}</span>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Verified</span>
            <span class="user-detail-value">${user.is_verified ? 'Yes' : 'No'}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Populate year filter
function populateYearFilter() {
  const yearSelect = document.getElementById('accounts-filter-year');
  if (!yearSelect) return;
  
  const years = new Set();
  allAccounts.forEach(user => {
    const year = new Date(user.created_at).getFullYear();
    years.add(year);
  });
  
  const sortedYears = Array.from(years).sort((a, b) => b - a);
  
  yearSelect.innerHTML = '<option value="">All Years</option>' + 
    sortedYears.map(year => `<option value="${year}">${year}</option>`).join('');
}

// Initialize accounts filters
function initAccountsFilters() {
  const applyBtn = document.getElementById('accounts-filter-apply');
  const resetBtn = document.getElementById('accounts-filter-reset');
  const refreshBtn = document.getElementById('accounts-refresh');
  const copyEmailsBtn = document.getElementById('accounts-copy-emails');
  const copyPhonesBtn = document.getElementById('accounts-copy-phones');
  const searchInput = document.getElementById('accounts-search-name');
  
  if (applyBtn) {
    applyBtn.addEventListener('click', applyAccountsFilters);
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', resetAccountsFilters);
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', loadAccountsData);
  }
  
  if (copyEmailsBtn) {
    copyEmailsBtn.addEventListener('click', copyAllEmails);
  }
  
  if (copyPhonesBtn) {
    copyPhonesBtn.addEventListener('click', copyAllPhones);
  }
  
  if (searchInput) {
    searchInput.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        applyAccountsFilters();
      }
    });
  }
}

// Apply accounts filters
function applyAccountsFilters() {
  const searchName = document.getElementById('accounts-search-name')?.value.trim().toLowerCase() || '';
  const filterMonth = document.getElementById('accounts-filter-month')?.value || '';
  const filterYear = document.getElementById('accounts-filter-year')?.value || '';
  
  filteredAccounts = allAccounts.filter(user => {
    // Name search
    if (searchName && !user.name.toLowerCase().includes(searchName)) {
      return false;
    }
    
    // Month filter
    if (filterMonth) {
      const userMonth = String(new Date(user.created_at).getMonth() + 1).padStart(2, '0');
      if (userMonth !== filterMonth) {
        return false;
      }
    }
    
    // Year filter
    if (filterYear) {
      const userYear = new Date(user.created_at).getFullYear().toString();
      if (userYear !== filterYear) {
        return false;
      }
    }
    
    return true;
  });
  
  renderAccountsList();
}

// Reset accounts filters
function resetAccountsFilters() {
  const searchInput = document.getElementById('accounts-search-name');
  const monthSelect = document.getElementById('accounts-filter-month');
  const yearSelect = document.getElementById('accounts-filter-year');
  
  if (searchInput) searchInput.value = '';
  if (monthSelect) monthSelect.value = '';
  if (yearSelect) yearSelect.value = '';
  
  filteredAccounts = [...allAccounts];
  renderAccountsList();
}

// Copy all emails
async function copyAllEmails() {
  const emails = filteredAccounts.map(user => user.email).filter(email => email).join('\n');
  
  if (!emails) {
    alert('No emails to copy');
    return;
  }
  
  try {
    await navigator.clipboard.writeText(emails);
    alert(`Copied ${filteredAccounts.length} email(s) to clipboard`);
  } catch (error) {
    console.error('Failed to copy emails:', error);
    alert('Failed to copy emails to clipboard');
  }
}

// Copy all phones
async function copyAllPhones() {
  const phones = filteredAccounts
    .map(user => {
      const phones = [];
      if (user.phone) phones.push(user.phone);
      if (user.phone2) phones.push(user.phone2);
      return phones;
    })
    .flat()
    .filter(phone => phone)
    .join('\n');
  
  if (!phones) {
    alert('No phones to copy');
    return;
  }
  
  try {
    await navigator.clipboard.writeText(phones);
    const phoneCount = phones.split('\n').length;
    alert(`Copied ${phoneCount} phone number(s) to clipboard`);
  } catch (error) {
    console.error('Failed to copy phones:', error);
    alert('Failed to copy phones to clipboard');
  }
}

// Delete user
async function deleteUser(userId) {
  if (!confirm('Are you sure you want to delete this user account? This action cannot be undone.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success) {
        // Remove from arrays
        allAccounts = allAccounts.filter(user => user.id !== userId);
        filteredAccounts = filteredAccounts.filter(user => user.id !== userId);
        renderAccountsList();
        alert('User account deleted successfully');
      } else {
        alert('Failed to delete user account: ' + (result.error || 'Unknown error'));
      }
    } else {
      alert('Failed to delete user account');
    }
  } catch (error) {
    console.error('Error deleting user:', error);
    alert('Error deleting user account');
  }
}

// Escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Export functions for global access
window.showSection = showSection;
window.deleteUser = deleteUser;
window.editRoom = (index) => { /* TODO: Implement edit room */ };
window.deleteRoom = (index) => { /* TODO: Implement delete room */ };
window.editMassage = (index) => { /* TODO: Implement edit massage */ };
window.deleteMassage = (index) => { /* TODO: Implement delete massage */ };
window.editYoga = (index) => { /* TODO: Implement edit yoga */ };
window.deleteYoga = (index) => { /* TODO: Implement delete yoga */ };
window.deleteImage = (index) => { /* TODO: Implement delete image */ };
// Massage booking management functions
async function confirmMassageBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this massage booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_massage_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Wellness booking confirmed successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm massage booking');
    }
  } catch (error) {
    console.error('Confirm massage booking error:', error);
    showStatus('Failed to confirm massage booking: ' + error.message, 'error');
  }
}

async function cancelMassageBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this massage booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_massage_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Wellness booking cancelled successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel massage booking');
    }
  } catch (error) {
    console.error('Cancel massage booking error:', error);
    showStatus('Failed to cancel massage booking: ' + error.message, 'error');
  }
}

async function deleteMassageBooking(bookingId) {
  if (!confirm('Are you sure you want to delete this massage booking? This action cannot be undone.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete_massage_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to delete massage booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Wellness booking deleted successfully!');
      loadBookingsData();
      updateBookingsDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to delete massage booking');
    }
  } catch (error) {
    console.error('Delete massage booking error:', error);
    showStatus('Failed to delete massage booking: ' + error.message, 'error');
  }
}

// Make functions globally available
window.confirmBooking = confirmBooking;
window.cancelBooking = cancelBooking;
window.deleteBooking = deleteBooking;
window.viewBookingDetails = viewBookingDetails;
window.unblockDate = unblockDate;
window.confirmMassageBooking = confirmMassageBooking;
window.cancelMassageBooking = cancelMassageBooking;
window.deleteMassageBooking = deleteMassageBooking;





