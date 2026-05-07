// Authentication system for Back to Base
/**
 * Absolute URL to api.php (same folder as the current page, or parent path via URL resolution).
 */
if (typeof window.btbApiPhp !== 'function') {
  window.btbApiPhp = function btbApiPhp() {
    try {
      return new URL('api.php', window.location.href).href;
    } catch (e) {
      return 'api.php';
    }
  };
}

/**
 * Guest session: JWT in HttpOnly cookie (set by api on login/register).
 * Legacy Bearer in localStorage is removed after a successful verify.
 */
(function () {
  if (typeof window.btbVerifyGuestSession === 'function') {
    return;
  }
  function mapUser(u) {
    if (!u || typeof u !== 'object') {
      return null;
    }
    return {
      id: u.id,
      email: u.email,
      name: u.name,
      phone: u.phone,
      phone2: u.phone2,
      isVerified: u.is_verified,
      createdAt: u.created_at,
      lastSession: u.last_session,
    };
  }

  async function verifyGuestSession() {
    let verifyUrl = 'api.php?action=verify';
    try {
      const u = new URL(window.btbApiPhp());
      u.searchParams.set('action', 'verify');
      verifyUrl = u.href;
    } catch (_) {}
    const baseHeaders = { 'Content-Type': 'application/json' };
    const opts = { method: 'GET', credentials: 'same-origin', headers: baseHeaders };
    let r = await fetch(verifyUrl, opts);
    let j = await r.json().catch(() => ({}));
    if (j.success && j.data && j.data.user) {
      try {
        localStorage.removeItem('btb_auth_token');
      } catch (_) {}
      return { ok: true, user: j.data.user };
    }
    let legacy = null;
    try {
      legacy = localStorage.getItem('btb_auth_token');
    } catch (_) {}
    if (legacy) {
      r = await fetch(verifyUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { ...baseHeaders, Authorization: 'Bearer ' + legacy },
      });
      j = await r.json().catch(() => ({}));
      if (j.success && j.data && j.data.user) {
        try {
          localStorage.removeItem('btb_auth_token');
        } catch (_) {}
        return { ok: true, user: j.data.user };
      }
    }
    return { ok: false, user: null };
  }

  function guestFetchInit(extra) {
    const o = extra && typeof extra === 'object' ? extra : {};
    const headers = { ...(o.headers || {}) };
    let legacy = null;
    try {
      legacy = localStorage.getItem('btb_auth_token');
    } catch (_) {}
    if (legacy && !headers.Authorization) {
      headers.Authorization = 'Bearer ' + legacy;
    }
    return { ...o, credentials: 'same-origin', headers };
  }

  window.btbMapVerifyUser = mapUser;
  window.btbVerifyGuestSession = verifyGuestSession;
  window.btbGuestFetchInit = guestFetchInit;
})();

class AuthSystem {
  constructor() {
    this.currentUser = null;
    this.isAuthenticated = false;
    this.init();
  }

  async init() {
    await this.checkAuthStatus();
    this.setupEventListeners();
    this.updateHeaderButtons();
  }

  // Checking authentication status (JWT in HttpOnly cookie; see auth_session.js)
  async checkAuthStatus() {
    if (typeof window.btbVerifyGuestSession !== 'function') {
      return;
    }
    try {
      const { ok, user } = await window.btbVerifyGuestSession();
      if (ok && user) {
        const mapped = window.btbMapVerifyUser ? window.btbMapVerifyUser(user) : user;
        this.currentUser = mapped;
        this.isAuthenticated = true;
        try {
          localStorage.setItem('btb_user_data', JSON.stringify(mapped));
        } catch (_) {}
      } else {
        this.currentUser = null;
        this.isAuthenticated = false;
        try {
          localStorage.removeItem('btb_user_data');
        } catch (_) {}
      }
    } catch (error) {
      console.error('Auth check error:', error);
      this.currentUser = null;
      this.isAuthenticated = false;
    }
  }

  // Setting up event handlers
  setupEventListeners() {
    // Switch between tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.switchTab(e.target.dataset.tab);
      });
    });

    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleLogin();
      });
    }

    // Registration form
    const regForm = document.getElementById('registration-form');
    if (regForm) {
      regForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleRegistration();
      });
    }

    // Password recovery form
    const forgotForm = document.getElementById('forgot-password-form');
    if (forgotForm) {
      forgotForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handlePasswordReset();
      });
    }

    // Links to switch forms
    const forgotLink = document.getElementById('forgot-password-link');
    if (forgotLink) {
      forgotLink.addEventListener('click', (e) => {
        e.preventDefault();
        this.showForgotPasswordForm();
      });
    }

    const backToLoginLink = document.getElementById('back-to-login-link');
    if (backToLoginLink) {
      backToLoginLink.addEventListener('click', (e) => {
        e.preventDefault();
        this.showLoginForm();
      });
    }
  }

  // Switch between tabs
  switchTab(tabName) {
    // Refresh the active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    // Show the corresponding form
    document.querySelectorAll('.auth-form').forEach(form => {
      form.classList.toggle('active', form.id === `${tabName}-form`);
    });

    // Clearing messages
    this.clearMessages();
  }

  // Login Processing
  async handleLogin() {
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    if (!email || !password) {
      this.showMessage('Please fill in all fields', 'error');
      return;
    }

    try {
      // Login via API
      await this.loginUser({ email, password });
      this.showMessage('Successfully signed in!', 'success');
      
      // Redirect to the main page of your personal account
      setTimeout(() => {
        window.location.href = 'dashboard.html';
      }, 1000);

    } catch (error) {
      this.showMessage('Login failed. Please try again.', 'error');
    }
  }

  // Processing registration
  async handleRegistration() {
    const name = document.getElementById('reg-name').value;
    const email = document.getElementById('reg-email').value;
    const phone = document.getElementById('reg-phone').value;
    const password = document.getElementById('reg-password').value;
    const confirmPassword = document.getElementById('reg-confirm-password').value;

    // Validation
    if (!name || !email || !phone || !password || !confirmPassword) {
      this.showMessage('Please fill in all fields', 'error');
      return;
    }

    if (password !== confirmPassword) {
      this.showMessage('Passwords do not match', 'error');
      return;
    }

    if (password.length < 6) {
      this.showMessage('Password must be at least 6 characters long', 'error');
      return;
    }

    try {
      // Checking to see if a user with the same email already exists
      const existingUser = await this.findUserByEmail(email);
      
      if (existingUser) {
        this.showMessage('An account with this email already exists. Please sign in.', 'error');
        this.switchTab('signin');
        document.getElementById('login-email').value = email;
        return;
      }

      // Create a new user via API
      const newUser = await this.createUser({ name, email, phone, password });
      
      // Automatically login the user after registration
      await this.loginUser({ email, password });
      
      this.showMessage('Account created successfully!', 'success');
      
      // Switch to the login form
      setTimeout(() => {
        this.switchTab('signin');
        document.getElementById('login-email').value = email;
        document.getElementById('login-password').value = '';
      }, 2000);

    } catch (error) {
      this.showMessage('Registration failed. Please try again.', 'error');
    }
  }

  // Password recovery processing
  async handlePasswordReset() {
    const email = document.getElementById('reset-email').value;

    if (!email) {
      this.showMessage('Please enter your email address', 'error');
      return;
    }

    try {
      // Checking if the user exists
      const user = await this.findUserByEmail(email);
      
      if (!user) {
        this.showMessage('No account found with this email address', 'error');
        return;
      }

      // We send a link to reset your password
      await this.sendPasswordResetEmail(email);
      
      this.showMessage('Password reset link sent to your email. Please check your inbox.', 'success');
      
      // Returning to the login form
      setTimeout(() => {
        this.showLoginForm();
      }, 2000);

    } catch (error) {
      this.showMessage('Failed to send reset link. Please try again.', 'error');
    }
  }

  // Search for a user by email
  async findUserByEmail(email) {
    try {
      let findUrl = `api.php?action=find_by_email&email=${encodeURIComponent(email)}`;
      try {
        const u = new URL(window.btbApiPhp());
        u.searchParams.set('action', 'find_by_email');
        u.searchParams.set('email', email);
        findUrl = u.href;
      } catch (_) {}
      const response = await fetch(findUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
      });
      
      const result = await response.json();
      
      if (result.success && result.data && result.data.exists) {
        return { email: result.data.email };
      }
      
      return null;
    } catch (error) {
      console.error('Find user error:', error);
      return null;
    }
  }

  // Creating a new user
  async createUser(userData) {
    try {
      const formData = new FormData();
      formData.append('action', 'register');
      formData.append('name', userData.name);
      formData.append('email', userData.email);
      formData.append('phone', userData.phone || '');
      formData.append('password', userData.password);
      
      const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
      const response = await fetch(
        apiHref,
        typeof window.btbGuestFetchInit === 'function'
          ? window.btbGuestFetchInit({ method: 'POST', body: formData })
          : { method: 'POST', body: formData, credentials: 'same-origin' },
      );

      const result = await response.json();

      if (result.success && result.data && result.data.user) {
        return result.data.user;
      } else {
        throw new Error(result.error || 'Failed to create user');
      }
    } catch (error) {
      console.error('Create user error:', error);
      throw error;
    }
  }

  // User Login
  async loginUser(user) {
    try {
      const formData = new FormData();
      formData.append('action', 'login');
      formData.append('email', user.email);
      formData.append('password', user.password);
      
      const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
      const response = await fetch(
        apiHref,
        typeof window.btbGuestFetchInit === 'function'
          ? window.btbGuestFetchInit({ method: 'POST', body: formData })
          : { method: 'POST', body: formData, credentials: 'same-origin' },
      );

      const result = await response.json();

      if (result.success && result.data && result.data.user) {
        const mapped = window.btbMapVerifyUser
          ? window.btbMapVerifyUser(result.data.user)
          : result.data.user;
        this.currentUser = mapped;
        this.isAuthenticated = true;

        try {
          localStorage.removeItem('btb_auth_token');
        } catch (_) {}
        localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));
        
        // Update the title
        this.updateHeaderButtons();
      } else {
        throw new Error(result.error || 'Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  }

  // User logout
  async logout() {
    try {
      const fd = new FormData();
      fd.append('action', 'logout');
      const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
      await fetch(
        apiHref,
        typeof window.btbGuestFetchInit === 'function'
          ? window.btbGuestFetchInit({ method: 'POST', body: fd })
          : { method: 'POST', body: fd, credentials: 'same-origin' },
      );
    } catch (_) {}

    this.currentUser = null;
    this.isAuthenticated = false;

    try {
      localStorage.removeItem('btb_auth_token');
      localStorage.removeItem('btb_user_data');
    } catch (_) {}

    this.updateHeaderButtons();
    
    // If we are on the orders page, we redirect to the main page
    if (window.location.pathname.includes('order.html')) {
      window.location.href = 'index.html';
    }
  }

  // Token Validation
  async validateToken() {
    if (typeof window.btbVerifyGuestSession !== 'function') {
      await this.logout();
      return false;
    }
    try {
      const { ok, user } = await window.btbVerifyGuestSession();
      if (ok && user) {
        const mapped = window.btbMapVerifyUser ? window.btbMapVerifyUser(user) : user;
        this.currentUser = mapped;
        this.isAuthenticated = true;
        localStorage.setItem('btb_user_data', JSON.stringify(mapped));
        return true;
      }
      await this.logout();
      return false;
    } catch (error) {
      console.error('Token validation error:', error);
      await this.logout();
      return false;
    }
  }

  // Sending a confirmation letter
  async sendConfirmationEmail(email, name) {
    // In a real application there would be an API for sending email
    console.log(`Confirmation email sent to ${email} for user ${name}`);
    
    // To demonstrate, we show the message
    this.showMessage(`Confirmation email sent to ${email}`, 'success');
  }

  // Sending an email to reset your password
  async sendPasswordResetEmail(email) {
    // In a real application there would be an API for sending email
    console.log(`Password reset email sent to ${email}`);
    
    // To demonstrate, we show the message
    this.showMessage(`Password reset email sent to ${email}`, 'success');
  }

  // Show password recovery form
  showForgotPasswordForm() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('forgot-password-form').style.display = 'block';
    
    // Fill in the email in the recovery form
    const loginEmail = document.getElementById('login-email').value;
    document.getElementById('reset-email').value = loginEmail;
  }

  // Show login form
  showLoginForm() {
    document.getElementById('forgot-password-form').style.display = 'none';
    document.getElementById('login-form').style.display = 'block';
  }

  // Updating buttons in the header and mobile menu
  updateHeaderButtons() {
    const signinBtn = document.getElementById('header-signin');
    const mobileSignin = document.getElementById('mobile-nav-signin');

    const apply = (el) => {
      if (!el) return;
      if (this.isAuthenticated) {
        el.textContent = 'My Account';
        el.href = 'dashboard.html';
        el.classList.add('authenticated');
      } else {
        el.textContent = 'Guest login';
        el.href = 'login.html';
        el.classList.remove('authenticated');
      }
    };

    apply(signinBtn);
    apply(mobileSignin);

    if (!signinBtn) {
      setTimeout(() => {
        const retryBtn = document.getElementById('header-signin');
        if (retryBtn) {
          this.updateHeaderButtons();
        }
      }, 100);
    }

    try {
      window.dispatchEvent(new CustomEvent('btb:auth:updated'));
    } catch (_) {}
  }

  // Show messages
  showMessage(message, type = 'info') {
    const messagesContainer = document.getElementById('auth-messages');
    if (!messagesContainer) return;

    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;

    messagesContainer.appendChild(messageEl);

    // Automatically remove the message after 5 seconds
    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }

  // Clearing messages
  clearMessages() {
    const messagesContainer = document.getElementById('auth-messages');
    if (messagesContainer) {
      messagesContainer.innerHTML = '';
    }
  }

  // Updating a user in the database
  async updateUserInDatabase() {
    if (!this.currentUser) {
      return;
    }
    
    try {
      const formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('name', this.currentUser.name || '');
      formData.append('phone', this.currentUser.phone || '');
      formData.append('phone2', this.currentUser.phone2 || '');

      const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
      const response = await fetch(
        apiHref,
        typeof window.btbGuestFetchInit === 'function'
          ? window.btbGuestFetchInit({ method: 'POST', body: formData })
          : { method: 'POST', body: formData, credentials: 'same-origin' },
      );

      const result = await response.json();

      if (result.success && result.data && result.data.user) {
        const mapped = window.btbMapVerifyUser
          ? window.btbMapVerifyUser(result.data.user)
          : result.data.user;
        this.currentUser = mapped;
        localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));
      }
    } catch (error) {
      console.error('Error updating user in database:', error);
    }
  }
}

// Initializing the authentication system
document.addEventListener('DOMContentLoaded', () => {
  // We create an instance only if it does not exist yet (avoid duplication)
  if (!window.authSystem) {
    window.authSystem = new AuthSystem();
  } else {
    // If an instance already exists, simply update the buttons
    window.authSystem.updateHeaderButtons();
  }
});

// Export for use in other files
window.AuthSystem = AuthSystem;
