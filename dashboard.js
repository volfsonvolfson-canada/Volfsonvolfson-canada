// Dashboard functionality for Back to Base
class Dashboard {
  constructor() {
    this.currentUser = null;
    this.init();
  }

  init() {
    this.checkAuth();
    this.setupEventListeners();
    this.loadUserData();
  }

  // Проверка аутентификации
  checkAuth() {
    const token = localStorage.getItem('btb_auth_token');
    const userData = localStorage.getItem('btb_user_data');
    
    if (!token || !userData) {
      // Пользователь не авторизован - перенаправляем на страницу входа
      window.location.href = 'login.html';
      return;
    }

    try {
      this.currentUser = JSON.parse(userData);
    } catch (error) {
      // Ошибка в данных пользователя - перенаправляем на страницу входа
      localStorage.removeItem('btb_auth_token');
      localStorage.removeItem('btb_user_data');
      window.location.href = 'login.html';
      return;
    }
  }

  // Настройка обработчиков событий
  setupEventListeners() {
    // Кнопка Account Information
    const accountInfoBtn = document.getElementById('btn-account-info');
    if (accountInfoBtn) {
      accountInfoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.showAccountInfo();
      });
    }

    // Кнопка выхода
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => {
        this.logout();
      });
    }

    // Кнопка редактирования
    const editDetailsBtn = document.getElementById('edit-details');
    if (editDetailsBtn) {
      editDetailsBtn.addEventListener('click', () => {
        this.showEditMode();
      });
    }

    // Кнопка сохранения изменений
    const saveChangesBtn = document.getElementById('save-changes');
    if (saveChangesBtn) {
      saveChangesBtn.addEventListener('click', () => {
        this.saveChanges();
      });
    }

    // Кнопка смены пароля
    const changePasswordBtn = document.getElementById('change-password');
    if (changePasswordBtn) {
      changePasswordBtn.addEventListener('click', () => {
        this.showPasswordModal();
      });
    }

    // Закрытие модального окна смены пароля
    const closePasswordModalBtn = document.getElementById('close-password-modal');
    if (closePasswordModalBtn) {
      closePasswordModalBtn.addEventListener('click', () => {
        this.hidePasswordModal();
      });
    }

    // Кнопка отмены смены пароля
    const cancelPasswordChangeBtn = document.getElementById('cancel-password-change');
    if (cancelPasswordChangeBtn) {
      cancelPasswordChangeBtn.addEventListener('click', () => {
        this.hidePasswordModal();
      });
    }

    // Форма смены пароля
    const changePasswordForm = document.getElementById('change-password-form');
    if (changePasswordForm) {
      changePasswordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handlePasswordChange();
      });
    }
  }

  // Загрузка данных пользователя
  loadUserData() {
    if (!this.currentUser) return;

    // Заполняем поля просмотра
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

    // Заполняем поля редактирования
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



  // Показ информации об аккаунте
  showAccountInfo() {
    const accountInfo = document.getElementById('account-info');
    if (accountInfo) {
      accountInfo.style.display = 'block';
    }
  }

  // Показ режима редактирования
  showEditMode() {
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');
    const changePasswordBtn = document.getElementById('change-password');
    const logoutBtn = document.getElementById('logout-btn');
    
    if (viewMode && editMode) {
      viewMode.style.display = 'none';
      editMode.style.display = 'block';
    }
    
    // Скрываем кнопки Change Password и Logout в режиме редактирования
    if (changePasswordBtn) changePasswordBtn.style.display = 'none';
    if (logoutBtn) logoutBtn.style.display = 'none';
  }

  // Показ режима просмотра
  showViewMode() {
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');
    const changePasswordBtn = document.getElementById('change-password');
    const logoutBtn = document.getElementById('logout-btn');
    
    if (viewMode && editMode) {
      viewMode.style.display = 'block';
      editMode.style.display = 'none';
    }
    
    // Показываем кнопки Change Password и Logout в режиме просмотра
    if (changePasswordBtn) changePasswordBtn.style.display = 'inline-block';
    if (logoutBtn) logoutBtn.style.display = 'inline-block';
  }

  // Сохранение изменений
  saveChanges() {
    const name = document.getElementById('edit-name').value;
    const phone2 = document.getElementById('edit-phone2').value;

    if (!name) {
      this.showMessage('Please fill in the Name field', 'error');
      return;
    }

    try {
      // Обновляем данные пользователя
      this.currentUser.name = name;
      this.currentUser.phone2 = phone2;
      this.currentUser.lastSession = new Date().toISOString();

      // Сохраняем в localStorage
      localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));

      // Обновляем пользователей в базе
      this.updateUserInDatabase();

      // Обновляем отображение
      this.loadUserData();

      // Возвращаемся в режим просмотра
      this.showViewMode();

      this.showMessage('Changes saved successfully!', 'success');
    } catch (error) {
      this.showMessage('Failed to save changes. Please try again.', 'error');
    }
  }

  // Обновление пользователя в базе
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

  // Показ модального окна смены пароля
  showPasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
      modal.style.display = 'flex';
      // Очищаем поля формы
      document.getElementById('current-password').value = '';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password').value = '';
      // Очищаем все ошибки
      this.clearPasswordErrors();
      // Фокус на первое поле
      document.getElementById('current-password').focus();
    }
  }

  // Скрытие модального окна смены пароля
  hidePasswordModal() {
    const modal = document.getElementById('password-modal');
    if (modal) {
      modal.style.display = 'none';
    }
  }

  // Обработка смены пароля
  handlePasswordChange() {
    // Очищаем все предыдущие ошибки
    this.clearPasswordErrors();

    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    let hasErrors = false;

    // Проверяем текущий пароль
    if (currentPassword !== this.currentUser.password) {
      this.showPasswordError('current-password', 'Current password is incorrect');
      hasErrors = true;
    }

    // Проверяем новый пароль
    if (!newPassword || newPassword.length < 6) {
      this.showPasswordError('new-password', 'New password must be at least 6 characters long');
      hasErrors = true;
    }

    // Проверяем подтверждение пароля
    if (newPassword !== confirmPassword) {
      this.showPasswordError('confirm-password', 'New passwords do not match');
      hasErrors = true;
    }

    if (hasErrors) {
      return;
    }

    try {
      // Обновляем пароль
      this.currentUser.password = newPassword;
      this.currentUser.lastSession = new Date().toISOString();

      // Сохраняем в localStorage
      localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));

      // Обновляем пользователей в базе
      this.updateUserInDatabase();

      // Скрываем модальное окно
      this.hidePasswordModal();

      // Показываем сообщение об успехе
      this.showMessage('Password changed successfully!', 'success');
    } catch (error) {
      this.showMessage('Failed to change password. Please try again.', 'error');
    }
  }

  // Показ ошибки для конкретного поля пароля
  showPasswordError(fieldId, message) {
    const errorElement = document.getElementById(`${fieldId}-error`);
    const inputElement = document.getElementById(fieldId);
    
    if (errorElement && inputElement) {
      errorElement.textContent = message;
      errorElement.classList.add('show');
      inputElement.classList.add('error');
    }
  }

  // Очистка всех ошибок паролей
  clearPasswordErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    const inputElements = document.querySelectorAll('.form-group input');
    
    errorElements.forEach(element => {
      element.classList.remove('show');
      element.textContent = '';
    });
    
    inputElements.forEach(element => {
      element.classList.remove('error');
    });
  }

  // Показ сообщений
  showMessage(message, type = 'info') {
    // Создаем временное сообщение
    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;
    messageEl.style.position = 'fixed';
    messageEl.style.top = '50%';
    messageEl.style.left = '50%';
    messageEl.style.transform = 'translate(-50%, -50%)';
    messageEl.style.zIndex = '1000';
    messageEl.style.maxWidth = '300px';
    messageEl.style.textAlign = 'center';

    document.body.appendChild(messageEl);

    // Автоматически убираем сообщение через 5 секунд
    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }

  // Выход из системы
  logout() {
    if (confirm('Are you sure you want to log out?')) {
      // Очищаем localStorage
      localStorage.removeItem('btb_auth_token');
      localStorage.removeItem('btb_user_data');
      
      // Перенаправляем на главную страницу
      window.location.href = 'index.html';
    }
  }
}

// Инициализация дашборда
document.addEventListener('DOMContentLoaded', () => {
  window.dashboard = new Dashboard();
});

// Экспорт для использования в других файлах
window.Dashboard = Dashboard;
