// Dashboard functionality for Back to Base
class Dashboard {
  constructor() {
    this.currentUser = null;
    void this.bootstrap();
  }

  async bootstrap() {
    if (!(await this.ensureGuestSession())) {
      return;
    }
    this.setupEventListeners();
    this.loadUserData();
  }

  /** @returns {Promise<boolean>} */
  async ensureGuestSession() {
    if (typeof window.btbVerifyGuestSession === 'function') {
      const { ok, user } = await window.btbVerifyGuestSession();
      if (!ok || !user) {
        window.location.href = 'login.html';
        return false;
      }
      const mapped = window.btbMapVerifyUser ? window.btbMapVerifyUser(user) : user;
      this.currentUser = mapped;
      try {
        localStorage.setItem('btb_user_data', JSON.stringify(mapped));
      } catch (_) {}
      return true;
    }
    const userData = localStorage.getItem('btb_user_data');
    const token = localStorage.getItem('btb_auth_token');
    if (!token || !userData) {
      window.location.href = 'login.html';
      return false;
    }
    try {
      this.currentUser = JSON.parse(userData);
    } catch (error) {
      try {
        localStorage.removeItem('btb_auth_token');
        localStorage.removeItem('btb_user_data');
      } catch (_) {}
      window.location.href = 'login.html';
      return false;
    }
    return true;
  }

  // Setting up event handlers
  setupEventListeners() {
    // Account Information button
    const accountInfoBtn = document.getElementById('btn-account-info');
    if (accountInfoBtn) {
      accountInfoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.showAccountInfo();
      });
    }

    // Exit button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => {
        this.logout();
      });
    }

    // Edit button
    const editDetailsBtn = document.getElementById('edit-details');
    if (editDetailsBtn) {
      editDetailsBtn.addEventListener('click', () => {
        this.showEditMode();
      });
    }

    // Save changes button
    const saveChangesBtn = document.getElementById('save-changes');
    if (saveChangesBtn) {
      saveChangesBtn.addEventListener('click', () => {
        this.saveChanges();
      });
    }

    // Change password button
    const changePasswordBtn = document.getElementById('change-password');
    if (changePasswordBtn) {
      changePasswordBtn.addEventListener('click', () => {
        this.showPasswordModal();
      });
    }

    // Closing the password change modal window
    const closePasswordModalBtn = document.getElementById('close-password-modal');
    if (closePasswordModalBtn) {
      closePasswordModalBtn.addEventListener('click', () => {
        this.hidePasswordModal();
      });
    }

    // Cancel password change button
    const cancelPasswordChangeBtn = document.getElementById('cancel-password-change');
    if (cancelPasswordChangeBtn) {
      cancelPasswordChangeBtn.addEventListener('click', () => {
        this.hidePasswordModal();
      });
    }

    // Password change form
    const changePasswordForm = document.getElementById('change-password-form');
    if (changePasswordForm) {
      changePasswordForm.dataset.hasSubmitHandler = 'true';
      changePasswordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        void this.handlePasswordChange();
      });
    }
  }

  // Loading user data
  loadUserData() {
    if (!this.currentUser) return;

    // Filling out the viewing fields
    const viewNameEl = document.getElementById('view-name');
    const viewEmailEl = document.getElementById('view-email');
    const viewPhoneEl = document.getElementById('view-phone');
    const viewPhone2El = document.getElementById('view-phone2');
    const viewCreatedEl = document.getElementById('view-created');
    const viewLastSessionEl = document.getElementById('view-last-session');

    if (viewNameEl) viewNameEl.textContent = this.currentUser.name || 'N/A';
    if (viewEmailEl) viewEmailEl.textContent = this.currentUser.email || 'N/A';
    if (viewPhoneEl) viewPhoneEl.textContent = this.currentUser.phone || 'N/A';
    if (viewPhone2El) viewPhone2El.textContent = this.currentUser.phone2 || 'N/A';

    if (viewCreatedEl && this.currentUser.createdAt) {
      const date = new Date(this.currentUser.createdAt);
      viewCreatedEl.textContent = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }

    if (viewLastSessionEl) {
      const lastSession = this.currentUser.lastSession || new Date().toISOString();
      const date = new Date(lastSession);
      viewLastSessionEl.textContent = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    // Filling out the editing fields
    const editNameEl = document.getElementById('edit-name');
    const editEmailEl = document.getElementById('edit-email');
    const editPhoneEl = document.getElementById('edit-phone');
    const editPhone2El = document.getElementById('edit-phone2');
    const editCreatedEl = document.getElementById('edit-created');
    const editLastSessionEl = document.getElementById('edit-last-session');

    if (editNameEl) editNameEl.value = this.currentUser.name || '';
    if (editEmailEl) editEmailEl.textContent = this.currentUser.email || 'N/A';
    if (editPhoneEl) editPhoneEl.textContent = this.currentUser.phone || 'N/A';
    if (editPhone2El) editPhone2El.value = this.currentUser.phone2 || '';

    if (editCreatedEl && this.currentUser.createdAt) {
      const date = new Date(this.currentUser.createdAt);
      editCreatedEl.textContent = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }

    if (editLastSessionEl) {
      const lastSession = this.currentUser.lastSession || new Date().toISOString();
      const date = new Date(lastSession);
      editLastSessionEl.textContent = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  }



  // Show account information
  showAccountInfo() {
    const accountInfo = document.getElementById('account-info');
    if (accountInfo) {
      accountInfo.style.display = 'block';
    }
  }

  // Show edit mode
  showEditMode() {
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');
    const changePasswordBtn = document.getElementById('change-password');
    const logoutBtn = document.getElementById('logout-btn');
    
    if (viewMode && editMode) {
      viewMode.style.display = 'none';
      editMode.style.display = 'block';
    }
    
    // Hiding the Change Password and Logout buttons in edit mode
    if (changePasswordBtn) changePasswordBtn.style.display = 'none';
    if (logoutBtn) logoutBtn.style.display = 'none';
  }

  // Show view mode
  showViewMode() {
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');
    const changePasswordBtn = document.getElementById('change-password');
    const logoutBtn = document.getElementById('logout-btn');
    
    if (viewMode && editMode) {
      viewMode.style.display = 'block';
      editMode.style.display = 'none';
    }
    
    // Showing the Change Password and Logout buttons in view mode
    if (changePasswordBtn) changePasswordBtn.style.display = 'inline-block';
    if (logoutBtn) logoutBtn.style.display = 'inline-block';
  }

  // Saving changes
  saveChanges() {
    const name = document.getElementById('edit-name').value;
    const phone2 = document.getElementById('edit-phone2').value;

    if (!name) {
      this.showMessage('Please fill in the Name field', 'error');
      return;
    }

    try {
      // Updating user data
      this.currentUser.name = name;
      this.currentUser.phone2 = phone2;
      this.currentUser.lastSession = new Date().toISOString();

      // Save to localStorage
      localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));

      // Updating users in the database
      this.updateUserInDatabase();

      // Updating the display
      this.loadUserData();

      // Returning to viewing mode
      this.showViewMode();

      this.showMessage('Changes saved successfully!', 'success');
    } catch (error) {
      this.showMessage('Failed to save changes. Please try again.', 'error');
    }
  }

  // Updating a user in the database
  updateUserInDatabase() {
    try {
      const users = JSON.parse(localStorage.getItem('btb_users') || '[]');
      const userIndex = users.findIndex(u => u.email === this.currentUser.email);
      
      if (userIndex >= 0) {
        users[userIndex] = { ...users[userIndex], ...this.currentUser };
        localStorage.setItem('btb_users', JSON.stringify(users));
      }
    } catch (error) {
      console.error('Error updating user in database:', error);
    }
  }

  // Show modal window for changing password
  showPasswordModal() {
    const modal = document.getElementById('password-modal');
    if (!modal) {
      return;
    }
    modal.style.display = 'flex';
    const cur = document.getElementById('current-password');
    const neu = document.getElementById('new-password');
    const conf = document.getElementById('confirm-password');
    if (cur) cur.value = '';
    if (neu) neu.value = '';
    if (conf) conf.value = '';
    this.clearPasswordErrors();
    if (cur) cur.focus();
  }

  // Hiding the password change modal window
  hidePasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  // Password change processing (the server checks the current password; the password is not stored in btb_user_data)
  async handlePasswordChange() {
    this.clearPasswordErrors();

    const curEl = document.getElementById('current-password');
    const newEl = document.getElementById('new-password');
    const confEl = document.getElementById('confirm-password');
    if (!curEl || !newEl || !confEl) {
      this.showMessage('Password form is missing. Please reload the page.', 'error');
      return;
    }

    const currentPassword = curEl.value;
    const newPassword = newEl.value;
    const confirmPassword = confEl.value;

    let hasErrors = false;

    if (!currentPassword) {
      this.showPasswordError('current-password', 'Enter your current password');
      hasErrors = true;
    }

    if (!newPassword || newPassword.length < 6) {
      this.showPasswordError('new-password', 'New password must be at least 6 characters long');
      hasErrors = true;
    }

    if (newPassword !== confirmPassword) {
      this.showPasswordError('confirm-password', 'New passwords do not match');
      hasErrors = true;
    }

    if (hasErrors) {
      return;
    }

    if (typeof window.btbVerifyGuestSession === 'function') {
      const { ok } = await window.btbVerifyGuestSession();
      if (!ok) {
        this.showMessage('Session expired. Please sign in again.', 'error');
        return;
      }
    }

    const submitBtn = document.querySelector('#change-password-form button[type="submit"]');
    const prevBtnText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving…';
    }

    try {
      const fd = new FormData();
      fd.append('action', 'change_password');
      fd.append('current_password', currentPassword);
      fd.append('new_password', newPassword);

      const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
      const res = await fetch(
        apiHref,
        typeof window.btbGuestFetchInit === 'function'
          ? window.btbGuestFetchInit({ method: 'POST', body: fd })
          : { method: 'POST', body: fd, credentials: 'same-origin' },
      );
      const json = await res.json().catch(() => ({}));

      if (!json.success) {
        const err = (json && json.error) ? String(json.error) : 'Could not change password';
        const low = err.toLowerCase();
        if (low.includes('current password')) {
          this.showPasswordError('current-password', err);
        } else if (low.includes('at least 6') || low.includes('new password')) {
          this.showPasswordError('new-password', err);
        } else {
          this.showMessage(err, 'error');
        }
        return;
      }

      try {
        delete this.currentUser.password;
      } catch (_) {}
      if (this.currentUser) {
        this.currentUser.lastSession = new Date().toISOString();
        localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));
      }

      curEl.value = '';
      newEl.value = '';
      confEl.value = '';
      this.hidePasswordModal();
      this.showMessage('Password changed successfully!', 'success');
    } catch (error) {
      this.showMessage((error && error.message) || 'Network error. Please try again.', 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = prevBtnText || 'Change Password';
      }
    }
  }

  // Showing an error for a specific password field
  showPasswordError(fieldId, message) {
    const errorElement = document.getElementById(`${fieldId}-error`);
    const inputElement = document.getElementById(fieldId);

    if (errorElement && inputElement) {
      errorElement.textContent = message;
      errorElement.classList.add('show');
      inputElement.classList.add('error');
    } else {
      this.showMessage(message, 'error');
    }
  }

  // Clearing errors only inside the password change modal
  clearPasswordErrors() {
    const modal = document.getElementById('password-modal');
    if (!modal) {
      return;
    }
    modal.querySelectorAll('.error-message').forEach((element) => {
      element.classList.remove('show');
      element.textContent = '';
    });
    modal.querySelectorAll('.form-group input').forEach((element) => {
      element.classList.remove('error');
    });
  }

  // Show messages
  showMessage(message, type = 'info') {
    // Create a temporary message
    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;
    messageEl.style.position = 'fixed';
    messageEl.style.top = '50%';
    messageEl.style.left = '50%';
    messageEl.style.transform = 'translate(-50%, -50%)';
    messageEl.style.zIndex = '100010';
    messageEl.style.maxWidth = '300px';
    messageEl.style.textAlign = 'center';

    document.body.appendChild(messageEl);

    // Automatically remove the message after 5 seconds
    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }

  // Logout
  logout() {
    if (!confirm('Are you sure you want to log out?')) {
      return;
    }
    void (async () => {
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
      try {
        localStorage.removeItem('btb_auth_token');
        localStorage.removeItem('btb_user_data');
      } catch (_) {}
      window.location.href = 'index.html';
    })();
  }
}

// Initializing the dashboard (immediately if the DOM is already ready - otherwise the submit will not be attached)
function initDashboardApp() {
  if (!window.dashboard) {
    window.dashboard = new Dashboard();
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDashboardApp);
} else {
  initDashboardApp();
}

// Export for use in other files
window.Dashboard = Dashboard;
