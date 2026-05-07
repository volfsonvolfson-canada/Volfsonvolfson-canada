// Common JavaScript utilities for Back to Base

// Error handling utilities
const ErrorHandler = {
  // Common error handling pattern
  handleError: function(error, context = '') {
    console.error(`Error in ${context}:`, error);
    return false;
  },

  // Safe try-catch wrapper
  safeExecute: function(fn, context = '') {
    try {
      return fn();
    } catch (error) {
      this.handleError(error, context);
      return null;
    }
  },

  // Async error handling
  safeAsync: async function(fn, context = '') {
    try {
      return await fn();
    } catch (error) {
      this.handleError(error, context);
      return null;
    }
  }
};

// DOM utilities
const DOMUtils = {
  // Safe element selection
  getElement: function(selector) {
    try {
      return document.querySelector(selector);
    } catch (error) {
      ErrorHandler.handleError(error, `getElement(${selector})`);
      return null;
    }
  },

  // Safe element selection (all)
  getElements: function(selector) {
    try {
      return document.querySelectorAll(selector);
    } catch (error) {
      ErrorHandler.handleError(error, `getElements(${selector})`);
      return [];
    }
  },

  // Safe event listener addition
  addEventListener: function(element, event, handler) {
    try {
      if (element && typeof handler === 'function') {
        element.addEventListener(event, handler);
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `addEventListener(${event})`);
      return false;
    }
  },

  // Safe class manipulation
  addClass: function(element, className) {
    try {
      if (element && className) {
        element.classList.add(className);
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `addClass(${className})`);
      return false;
    }
  },

  removeClass: function(element, className) {
    try {
      if (element && className) {
        element.classList.remove(className);
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `removeClass(${className})`);
      return false;
    }
  },

  toggleClass: function(element, className) {
    try {
      if (element && className) {
        element.classList.toggle(className);
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `toggleClass(${className})`);
      return false;
    }
  },

  // Safe style manipulation
  setStyle: function(element, property, value) {
    try {
      if (element && property && value !== undefined) {
        element.style[property] = value;
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `setStyle(${property})`);
      return false;
    }
  },

  // Safe attribute manipulation
  setAttribute: function(element, attribute, value) {
    try {
      if (element && attribute && value !== undefined) {
        element.setAttribute(attribute, value);
        return true;
      }
      return false;
    } catch (error) {
      ErrorHandler.handleError(error, `setAttribute(${attribute})`);
      return false;
    }
  }
};

// Form utilities
const FormUtils = {
  // Safe form validation
  validateForm: function(form) {
    try {
      if (!form) return false;
      
      const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
      let isValid = true;
      
      inputs.forEach(input => {
        if (!input.value.trim()) {
          this.flashInvalid(input);
          isValid = false;
        }
      });
      
      return isValid;
    } catch (error) {
      ErrorHandler.handleError(error, 'validateForm');
      return false;
    }
  },

  // Flash invalid field
  flashInvalid: function(element) {
    try {
      if (!element) return;
      
      DOMUtils.removeClass(element, 'flash-invalid');
      void element.offsetWidth; // restart animation
      DOMUtils.addClass(element, 'flash-invalid');
      
      // Auto-clear after animation
      setTimeout(() => {
        DOMUtils.removeClass(element, 'flash-invalid');
      }, 700);
    } catch (error) {
      ErrorHandler.handleError(error, 'flashInvalid');
    }
  },

  // Clear form
  clearForm: function(form) {
    try {
      if (!form) return false;
      
      const inputs = form.querySelectorAll('input, textarea, select');
      inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
          input.checked = false;
        } else {
          input.value = '';
        }
      });
      
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'clearForm');
      return false;
    }
  },

  // Get form data
  getFormData: function(form) {
    try {
      if (!form) return {};
      
      const formData = new FormData(form);
      const data = {};
      
      for (let [key, value] of formData.entries()) {
        data[key] = value;
      }
      
      return data;
    } catch (error) {
      ErrorHandler.handleError(error, 'getFormData');
      return {};
    }
  }
};

// LocalStorage utilities
const StorageUtils = {
  // Safe localStorage operations
  setItem: function(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, `setItem(${key})`);
      return false;
    }
  },

  getItem: function(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key);
      if (item == null) return defaultValue;
      try {
        return JSON.parse(item);
      } catch (_) {
        // Value was stored as a plain string (e.g., 'light'/'dark')
        return item;
      }
    } catch (error) {
      ErrorHandler.handleError(error, `getItem(${key})`);
      return defaultValue;
    }
  },

  removeItem: function(key) {
    try {
      localStorage.removeItem(key);
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, `removeItem(${key})`);
      return false;
    }
  },

  clear: function() {
    try {
      localStorage.clear();
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'clear');
      return false;
    }
  }
};

// Animation utilities
const AnimationUtils = {
  // Reveal elements on scroll
  initReveal: function() {
    try {
      const elements = DOMUtils.getElements('.reveal');
      if (!elements.length) return;

      const reveal = () => {
        const trigger = window.innerHeight * 0.88;
        elements.forEach(el => {
          const rect = el.getBoundingClientRect();
          if (rect.top < trigger) {
            DOMUtils.addClass(el, 'visible');
          }
        });
      };

      reveal();
      window.addEventListener('scroll', reveal, { passive: true });
    } catch (error) {
      ErrorHandler.handleError(error, 'initReveal');
    }
  },

  // Fade in element
  fadeIn: function(element, duration = 300) {
    try {
      if (!element) return false;
      
      DOMUtils.setStyle(element, 'opacity', '0');
      DOMUtils.setStyle(element, 'transition', `opacity ${duration}ms ease-in`);
      
      setTimeout(() => {
        DOMUtils.setStyle(element, 'opacity', '1');
      }, 10);
      
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'fadeIn');
      return false;
    }
  },

  // Slide up element
  slideUp: function(element, duration = 300) {
    try {
      if (!element) return false;
      
      DOMUtils.setStyle(element, 'transform', 'translateY(20px)');
      DOMUtils.setStyle(element, 'opacity', '0');
      DOMUtils.setStyle(element, 'transition', `all ${duration}ms ease-out`);
      
      setTimeout(() => {
        DOMUtils.setStyle(element, 'transform', 'translateY(0)');
        DOMUtils.setStyle(element, 'opacity', '1');
      }, 10);
      
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'slideUp');
      return false;
    }
  }
};

// Theme utilities
const ThemeUtils = {
  // Get current theme
  getCurrentTheme: function() {
    try {
      return document.documentElement.getAttribute('data-theme') || 'dark';
    } catch (error) {
      ErrorHandler.handleError(error, 'getCurrentTheme');
      return 'dark';
    }
  },

  // Set theme
  // NOTE: This method saves theme directly to localStorage (not through StorageUtils)
  // to ensure consistency with ThemeManager.toggleTheme() which also saves directly
  setTheme: function(theme) {
    try {
      document.documentElement.setAttribute('data-theme', theme);
      // Save theme directly to localStorage (not through StorageUtils) for consistency
      // ThemeManager.toggleTheme() also saves directly: localStorage.setItem('btb_theme', this.currentTheme)
      localStorage.setItem('btb_theme', theme);
      // Mark that user explicitly set theme so inline init respects it
      try { localStorage.setItem('btb_theme_user', '1'); } catch (_) {}
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, `setTheme(${theme})`);
      return false;
    }
  },

  // Toggle theme
  toggleTheme: function() {
    try {
      const currentTheme = this.getCurrentTheme();
      // Cycle through themes: dark -> twilight -> light -> dark
      let newTheme;
      if (currentTheme === 'dark') {
        newTheme = 'twilight';
      } else if (currentTheme === 'twilight') {
        newTheme = 'light';
      } else {
        newTheme = 'dark';
      }
      return this.setTheme(newTheme);
    } catch (error) {
      ErrorHandler.handleError(error, 'toggleTheme');
      return false;
    }
  },

  // Initialize theme
  initTheme: function() {
    try {
      const savedTheme = StorageUtils.getItem('btb_theme', 'dark');
      this.setTheme(savedTheme);
      
      // Update theme toggle button text
      const themeText = DOMUtils.getElement('#theme-text');
      if (themeText) {
        if (savedTheme === 'dark') {
          themeText.textContent = 'Twilight';
        } else if (savedTheme === 'twilight') {
          themeText.textContent = 'Light';
        } else {
          themeText.textContent = 'Dark';
        }
      }
      
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'initTheme');
      return false;
    }
  }
};

// Date utilities
const DateUtils = {
  // Parse local date (avoiding timezone issues)
  parseLocalDate: function(dateString) {
    try {
      if (!dateString) return null;
      
      const [year, month, day] = dateString.split('-').map(Number);
      return new Date(year, month - 1, day);
    } catch (error) {
      ErrorHandler.handleError(error, `parseLocalDate(${dateString})`);
      return null;
    }
  },

  // Format date for display
  formatDate: function(date, options = {}) {
    try {
      if (!date) return '';
      
      const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      };
      
      return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(date);
    } catch (error) {
      ErrorHandler.handleError(error, 'formatDate');
      return '';
    }
  },

  // Get date string in YYYY-MM-DD format
  getDateString: function(date) {
    try {
      if (!date) return '';
      
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      
      return `${year}-${month}-${day}`;
    } catch (error) {
      ErrorHandler.handleError(error, 'getDateString');
      return '';
    }
  }
};

// Validation bubble utilities
const ValidationUtils = {
  // Show validation bubble
  showBubble: function(target, message) {
    try {
      // DEBUG: Log all calls to ValidationUtils.showBubble
      console.trace('🔍 ValidationUtils.showBubble called:', {
        message: message,
        target: target,
        targetId: target?.id,
        targetName: target?.name,
        targetClass: target?.className,
        stack: new Error().stack
      });
      
      if (!target || !message) return false;
      
      // Remove existing bubble
      this.hideBubble();
      
      const bubble = document.createElement('div');
      bubble.className = 'btb-bubble';
      bubble.innerHTML = `
        <div class="btb-ic">!</div>
        <div class="btb-msg">${message}</div>
      `;
      
      document.body.appendChild(bubble);
      
      // CRITICAL: Flatpickr with altInput is used for date fields
      // The original input is hidden (1px x 1px), so you need to use the visible altInput for positioning
      // IMPORTANT: If target is already an altInput (passed from showDateErrorNotification), use it directly
      // If target is the original input, look for altInput via _flatpickr
      let targetInput = target;
      
      // Check if target is already altInput (altInput does not have _flatpickr, but there is a class flatpickr-input)
      // If target is the original input, it has _flatpickr
      if (typeof flatpickr !== 'undefined' && target._flatpickr) {
        // target - original input, look for altInput
        const fpInstance = target._flatpickr;
        if (fpInstance.altInput) {
          targetInput = fpInstance.altInput;
        }
      }
      // If target is already altInput (no _flatpickr), use it directly
      
      // Position bubble - we use EXACTLY THE SAME positioning logic as in fallback showDateErrorNotification
      // This guarantees identical behavior for the first and second messages
      setTimeout(() => {
        const rect = targetInput.getBoundingClientRect();
        const windowWidth = window.innerWidth || document.documentElement.clientWidth;
        
        // Getting the real notification sizes after rendering
        const bubbleWidth = bubble.offsetWidth || 360;
        const bubbleHeight = bubble.offsetHeight || 100;
        
        // Vertical position - IMMEDIATELY BELOW the input (EXACTLY as in fallback showDateErrorNotification, line 499)
        const top = rect.bottom + 8; // 8px space below the input
        
        // Horizontal position - EXACTLY as in fallback showDateErrorNotification (line 503)
        // Math.min ensures that the notification does not extend beyond the right edge of the viewport
        const left = Math.min(windowWidth - bubbleWidth - 8, rect.right + 12);
        
        // Make sure left is not negative (EXACTLY like in fallback, line 506)
        const finalLeft = Math.max(8, left);
        
        bubble.style.top = `${top}px`;
        bubble.style.left = `${finalLeft}px`;
      }, 0);
      
      // Auto-hide after 3 seconds
      setTimeout(() => {
        this.hideBubble();
      }, 3000);
      
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'showBubble');
      return false;
    }
  },

  // Hide validation bubble
  hideBubble: function() {
    try {
      const existingBubble = DOMUtils.getElement('.btb-bubble');
      if (existingBubble) {
        existingBubble.remove();
      }
      return true;
    } catch (error) {
      ErrorHandler.handleError(error, 'hideBubble');
      return false;
    }
  }
};

// Initialize common utilities when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  try {
    // NOTE: Theme initialization is handled by ThemeManager in script.js
    // ThemeUtils is kept for backward compatibility but not auto-initialized
    
    // Initialize reveal animations
    AnimationUtils.initReveal();
    
    // NOTE: Theme toggle is handled by ThemeManager in script.js
    // No need to initialize theme toggle here
  } catch (error) {
    ErrorHandler.handleError(error, 'DOMContentLoaded initialization');
  }
});

// Export utilities for use in other files
window.ErrorHandler = ErrorHandler;
window.DOMUtils = DOMUtils;
window.FormUtils = FormUtils;
window.StorageUtils = StorageUtils;
window.AnimationUtils = AnimationUtils;
window.ThemeUtils = ThemeUtils;
window.DateUtils = DateUtils;
window.ValidationUtils = ValidationUtils;

/**
 * Admin CMS: scaled JPEG via admin_image_thumb.php so previews load faster than full assets/.
 * Shared by admin.js and admin-content.js (both load utils.js first).
 */
var BTB_ADMIN_PREVIEW_THUMB_W = 520;

/**
 * @param {string|null|undefined} pathOrUrl
 * @param {boolean} [bustCache]
 */
function btbAdminDisplayUrlForAsset(pathOrUrl, bustCache) {
  if (pathOrUrl == null) {
    return '';
  }
  const raw = String(pathOrUrl).trim();
  if (!raw) {
    return '';
  }
  if (raw.startsWith('data:') || raw.startsWith('blob:')) {
    return raw;
  }
  const path = btbAdminStripAssetPath(raw);
  if (path && path.indexOf('assets/') === 0) {
    let out =
      'admin_image_thumb.php?p=' +
      encodeURIComponent(path) +
      '&w=' +
      BTB_ADMIN_PREVIEW_THUMB_W;
    if (bustCache) {
      out += '&v=' + Date.now();
    }
    return out;
  }
  if (!bustCache) {
    return raw;
  }
  const base = raw.split('&v=')[0].split('?v=')[0];
  const sep = base.indexOf('?') >= 0 ? '&' : '?';
  return base + sep + 'v=' + Date.now();
}

function btbAdminStripAssetPath(url) {
  let s = String(url || '').trim();
  if (!s) return '';
  try {
    const u = new URL(s, window.location.href);
    const leaf = u.pathname.split('/').pop() || '';
    if (leaf === 'admin_image_thumb.php') {
      const inner = u.searchParams.get('p');
      if (inner && String(inner).indexOf('assets/') >= 0) {
        return String(inner).split('?')[0].replace(/^\/+/, '');
      }
    }
  } catch (e) {
    // relative or invalid URL — fall through
  }
  s = s.split('?')[0];
  const idx = s.indexOf('assets/');
  if (idx >= 0) {
    s = s.slice(idx);
  }
  return s.replace(/^\/+/, '');
}

/** Matches btb_upload_image_max_file_bytes() in common.php (admin uploads). */
var BTB_ADMIN_MAX_UPLOAD_BYTES = 32 * 1024 * 1024;

/**
 * Client-side check before upload_image.php (MIME/extension + max bytes).
 * @param {File|null|undefined} file
 * @returns {string|null} Error message, or null if OK.
 */
function btbAdminValidateImageUploadFile(file) {
  if (!file) {
    return 'No file selected.';
  }
  const okMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/pjpeg'];
  const type = String(file.type || '').toLowerCase();
  const name = String(file.name || '').toLowerCase();
  const extOk = /\.(jpe?g|png|gif)$/i.test(name);
  if (type && okMime.indexOf(type) === -1 && !extOk) {
    return 'Invalid file type. Only JPEG, PNG, and GIF are allowed.';
  }
  const size = typeof file.size === 'number' ? file.size : 0;
  if (size > BTB_ADMIN_MAX_UPLOAD_BYTES) {
    const mb = Math.max(1, Math.round(BTB_ADMIN_MAX_UPLOAD_BYTES / (1024 * 1024)));
    return `File is too large. Maximum size is ${mb} MB before upload (the server will resize after upload).`;
  }
  return null;
}
