/**
 * Universal Theme Initialization
 * This script MUST be loaded synchronously in <head> before CSS/styles to prevent theme flicker
 * Works on ALL pages - user can land on any page first
 */

(function() {
  'use strict';
  
  /**
   * Initialize theme immediately before page render
   * This runs synchronously in <head> before any CSS or DOM rendering
   */
  function initTheme() {
    try {
      // Get saved theme from localStorage
      const savedTheme = localStorage.getItem('btb_theme');
      
      // Check if user has explicitly set a theme preference
      const userSetTheme = localStorage.getItem('btb_theme_user') === '1';
      
      // Determine initial theme:
      // - If user has explicitly set a theme (userSetTheme = true) AND savedTheme is valid, use savedTheme
      // - Otherwise, default to 'dark' (first-time visitor or invalid theme)
      let initialTheme = 'dark'; // Default for first-time visitors
      
      if (userSetTheme && savedTheme && (savedTheme === 'dark' || savedTheme === 'light' || savedTheme === 'twilight')) {
        // User has explicitly chosen a theme and it's valid - use it
        initialTheme = savedTheme;
      }
      // If userSetTheme is false or savedTheme is invalid, keep default 'dark'
      
      // Set theme attribute immediately before page renders
      document.documentElement.setAttribute('data-theme', initialTheme);
      
      // Also store current theme for ThemeManager to read later
      // This ensures consistency if ThemeManager initializes before page fully loads
      if (!window.__BTB_THEME_INIT) {
        window.__BTB_THEME_INIT = {
          theme: initialTheme,
          userSet: userSetTheme
        };
      }
      
    } catch (error) {
      // If localStorage fails, default to dark theme
      console.error('Theme initialization error:', error);
      document.documentElement.setAttribute('data-theme', 'dark');
    }
  }
  
  // Run immediately - this script is loaded synchronously in <head>
  initTheme();
})();

