// Authentication system for Back to Base
class AuthSystem {
  constructor() {
    this.currentUser = null;
    this.isAuthenticated = false;
    this.init();
  }

  init() {
    this.checkAuthStatus();
    this.setupEventListeners();
    this.updateHeaderButtons();
  }

  // Проверка статуса аутентификации
  checkAuthStatus() {
    const token = localStorage.getItem('btb_auth_token');
    const userData = localStorage.getItem('btb_user_data');
    
    if (token && userData) {
      try {
        this.currentUser = JSON.parse(userData);
        this.isAuthenticated = true;
        this.validateToken();
      } catch (error) {
        this.logout();
      }
    }
  }

  // Настройка обработчиков событий
  setupEventListeners() {
    // Переключение между вкладками
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        this.switchTab(e.target.dataset.tab);
      });
    });

    // Форма входа
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleLogin();
      });
    }

    // Форма регистрации
    const regForm = document.getElementById('registration-form');
    if (regForm) {
      regForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleRegistration();
      });
    }

    // Форма восстановления пароля
    const forgotForm = document.getElementById('forgot-password-form');
    if (forgotForm) {
      forgotForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handlePasswordReset();
      });
    }

    // Ссылки для переключения форм
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

  // Переключение между вкладками
  switchTab(tabName) {
    // Обновляем активную вкладку
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabName);
    });

    // Показываем соответствующую форму
    document.querySelectorAll('.auth-form').forEach(form => {
      form.classList.toggle('active', form.id === `${tabName}-form`);
    });

    // Очищаем сообщения
    this.clearMessages();
  }

  // Обработка входа
  async handleLogin() {
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    if (!email || !password) {
      this.showMessage('Please fill in all fields', 'error');
      return;
    }

    try {
      // Проверяем, есть ли пользователь в базе
      const user = await this.findUserByEmail(email);
      
      if (!user) {
        // Пользователя нет - предлагаем зарегистрироваться
        this.showMessage('No account found with this email. Please create an account.', 'info');
        this.switchTab('register');
        // Заполняем email в форме регистрации
        document.getElementById('reg-email').value = email;
        return;
      }

      // Проверяем пароль
      if (user.password !== password) {
        // Пароль не подходит
        this.showMessage('Incorrect password. Please try again.', 'error');
        // Очищаем только поле пароля, email оставляем
        document.getElementById('login-password').value = '';
        // Показываем кнопку восстановления пароля
        this.showForgotPasswordForm();
        return;
      }

      // Успешный вход
      await this.loginUser(user);
      this.showMessage('Successfully signed in!', 'success');
      
      // Перенаправляем на главную страницу личного кабинета
      setTimeout(() => {
        window.location.href = 'dashboard.html';
      }, 1000);

    } catch (error) {
      this.showMessage('Login failed. Please try again.', 'error');
    }
  }

  // Обработка регистрации
  async handleRegistration() {
    const name = document.getElementById('reg-name').value;
    const email = document.getElementById('reg-email').value;
    const phone = document.getElementById('reg-phone').value;
    const password = document.getElementById('reg-password').value;
    const confirmPassword = document.getElementById('reg-confirm-password').value;

    // Валидация
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
      // Проверяем, не существует ли уже пользователь с таким email
      const existingUser = await this.findUserByEmail(email);
      
      if (existingUser) {
        this.showMessage('An account with this email already exists. Please sign in.', 'error');
        this.switchTab('signin');
        document.getElementById('login-email').value = email;
        return;
      }

      // Создаем нового пользователя
      const newUser = await this.createUser({ name, email, phone, password });
      
      // Отправляем подтверждающее письмо
      await this.sendConfirmationEmail(email, name);
      
      this.showMessage('Account created successfully! Please check your email for confirmation.', 'success');
      
      // Переключаемся на форму входа
      setTimeout(() => {
        this.switchTab('signin');
        document.getElementById('login-email').value = email;
        document.getElementById('login-password').value = '';
      }, 2000);

    } catch (error) {
      this.showMessage('Registration failed. Please try again.', 'error');
    }
  }

  // Обработка восстановления пароля
  async handlePasswordReset() {
    const email = document.getElementById('reset-email').value;

    if (!email) {
      this.showMessage('Please enter your email address', 'error');
      return;
    }

    try {
      // Проверяем, существует ли пользователь
      const user = await this.findUserByEmail(email);
      
      if (!user) {
        this.showMessage('No account found with this email address', 'error');
        return;
      }

      // Отправляем ссылку для сброса пароля
      await this.sendPasswordResetEmail(email);
      
      this.showMessage('Password reset link sent to your email. Please check your inbox.', 'success');
      
      // Возвращаемся к форме входа
      setTimeout(() => {
        this.showLoginForm();
      }, 2000);

    } catch (error) {
      this.showMessage('Failed to send reset link. Please try again.', 'error');
    }
  }

  // Поиск пользователя по email
  async findUserByEmail(email) {
    // В реальном приложении здесь был бы API запрос
    // Сейчас используем localStorage для демонстрации
    const users = JSON.parse(localStorage.getItem('btb_users') || '[]');
    return users.find(user => user.email === email);
  }

  // Создание нового пользователя
  async createUser(userData) {
    // В реальном приложении здесь был бы API запрос
    const users = JSON.parse(localStorage.getItem('btb_users') || '[]');
    
    const newUser = {
      id: `user_${Date.now()}_${Math.random().toString(36).slice(2,8)}`,
      ...userData,
      phone2: '', // Дополнительный телефон по умолчанию пустой
      lastSession: new Date().toISOString(),
      createdAt: new Date().toISOString(),
      isVerified: false
    };
    
    users.push(newUser);
    localStorage.setItem('btb_users', JSON.stringify(users));
    
    return newUser;
  }

  // Вход пользователя
  async loginUser(user) {
    this.currentUser = user;
    this.isAuthenticated = true;
    
    // Обновляем время последнего сеанса
    this.currentUser.lastSession = new Date().toISOString();
    
    // Создаем токен аутентификации
    const token = `token_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;
    
    // Сохраняем в localStorage
    localStorage.setItem('btb_auth_token', token);
    localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));
    
    // Обновляем пользователя в базе
    this.updateUserInDatabase();
    
    // Обновляем заголовок
    this.updateHeaderButtons();
  }

  // Выход пользователя
  logout() {
    this.currentUser = null;
    this.isAuthenticated = false;
    
    // Очищаем localStorage
    localStorage.removeItem('btb_auth_token');
    localStorage.removeItem('btb_user_data');
    
    // Обновляем заголовок
    this.updateHeaderButtons();
    
    // Если мы на странице заказов, перенаправляем на главную
    if (window.location.pathname.includes('order.html')) {
      window.location.href = 'index.html';
    }
  }

  // Валидация токена
  async validateToken() {
    // В реальном приложении здесь была бы проверка токена на сервере
    // Сейчас просто проверяем наличие токена
    const token = localStorage.getItem('btb_auth_token');
    if (!token) {
      this.logout();
      return false;
    }
    return true;
  }

  // Отправка подтверждающего письма
  async sendConfirmationEmail(email, name) {
    // В реальном приложении здесь был бы API для отправки email
    console.log(`Confirmation email sent to ${email} for user ${name}`);
    
    // Для демонстрации показываем сообщение
    this.showMessage(`Confirmation email sent to ${email}`, 'success');
  }

  // Отправка письма для сброса пароля
  async sendPasswordResetEmail(email) {
    // В реальном приложении здесь был бы API для отправки email
    console.log(`Password reset email sent to ${email}`);
    
    // Для демонстрации показываем сообщение
    this.showMessage(`Password reset email sent to ${email}`, 'success');
  }

  // Показ формы восстановления пароля
  showForgotPasswordForm() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('forgot-password-form').style.display = 'block';
    
    // Заполняем email в форме восстановления
    const loginEmail = document.getElementById('login-email').value;
    document.getElementById('reset-email').value = loginEmail;
  }

  // Показ формы входа
  showLoginForm() {
    document.getElementById('forgot-password-form').style.display = 'none';
    document.getElementById('login-form').style.display = 'block';
  }

  // Обновление кнопок в заголовке
  updateHeaderButtons() {
    const signinBtn = document.getElementById('header-signin');
    if (!signinBtn) {
      // Если кнопка еще не загружена, попробуем еще раз через небольшую задержку
      setTimeout(() => {
        const retryBtn = document.getElementById('header-signin');
        if (retryBtn) {
          this.updateHeaderButtons();
        }
      }, 100);
      return;
    }

    if (this.isAuthenticated) {
      signinBtn.innerHTML = 'My Account';
      signinBtn.href = 'dashboard.html';
      signinBtn.classList.add('authenticated');
    } else {
      signinBtn.innerHTML = 'Sign In';
      signinBtn.href = 'login.html';
      signinBtn.classList.remove('authenticated');
    }
  }

  // Показ сообщений
  showMessage(message, type = 'info') {
    const messagesContainer = document.getElementById('auth-messages');
    if (!messagesContainer) return;

    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;

    messagesContainer.appendChild(messageEl);

    // Автоматически убираем сообщение через 5 секунд
    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }

  // Очистка сообщений
  clearMessages() {
    const messagesContainer = document.getElementById('auth-messages');
    if (messagesContainer) {
      messagesContainer.innerHTML = '';
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
}

// Инициализация системы аутентификации
document.addEventListener('DOMContentLoaded', () => {
  // Создаем экземпляр только если его еще нет (избегаем дублирования)
  if (!window.authSystem) {
    window.authSystem = new AuthSystem();
  } else {
    // Если экземпляр уже существует, просто обновляем кнопки
    window.authSystem.updateHeaderButtons();
  }
});

// Экспорт для использования в других файлах
window.AuthSystem = AuthSystem;
