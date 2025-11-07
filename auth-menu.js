/**
 * Универсальная функция для создания меню Sign In / Create Account
 * Основана на структуре login.html
 * 
 * @param {string|HTMLElement} container - Селектор или элемент контейнера для меню
 * @param {Object} options - Опции конфигурации
 * @param {string} options.defaultTab - Вкладка по умолчанию ('signin' или 'register')
 * @param {Function} options.onLogin - Callback при успешном входе
 * @param {Function} options.onRegister - Callback при успешной регистрации
 * @param {Function} options.onPasswordReset - Callback при запросе сброса пароля
 * @param {Object} options.prefillData - Данные для предзаполнения полей {name, email, phone}
 */
function createAuthMenu(container, options = {}) {
  const {
    defaultTab = 'signin',
    onLogin = null,
    onRegister = null,
    onPasswordReset = null,
    prefillData = null
  } = options;

  // Получаем контейнер
  const containerEl = typeof container === 'string' 
    ? document.querySelector(container) 
    : container;

  if (!containerEl) {
    console.error('Auth menu: Container not found');
    return null;
  }

  // Создаем HTML структуру
  const authHTML = `
    <div class="auth-container">
      <div class="auth-tabs">
        <button class="tab-btn ${defaultTab === 'signin' ? 'active' : ''}" data-tab="signin">Sign In</button>
        <button class="tab-btn ${defaultTab === 'register' ? 'active' : ''}" data-tab="register">Create Account</button>
      </div>
      
      <!-- Форма входа -->
      <div id="signin-form" class="auth-form ${defaultTab === 'signin' ? 'active' : ''}">
        <form id="login-form">
          <div class="form-row">
            <div>
              <label for="login-email">Email address</label>
              <input id="login-email" name="email" type="email" required 
                     placeholder="Enter your email" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="login-password">Password</label>
              <input id="login-password" name="password" type="password" required 
                     placeholder="Enter your password" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <button type="submit" class="btn primary">Sign In</button>
            </div>
          </div>
          <div class="form-row">
            <div>
              <a href="#" class="forgot-password" id="forgot-password-link">Forgot password?</a>
            </div>
          </div>
        </form>
        
        <!-- Форма восстановления пароля -->
        <form id="forgot-password-form" style="display: none;">
          <div class="form-row">
            <div>
              <label for="reset-email">Email address</label>
              <input id="reset-email" name="email" type="email" required 
                     placeholder="Enter your email" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <button type="submit" class="btn primary">Send Reset Link</button>
            </div>
          </div>
          <div class="form-row">
            <div>
              <a href="#" class="back-to-login" id="back-to-login-link">← Back to Sign In</a>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Форма регистрации -->
      <div id="register-form" class="auth-form ${defaultTab === 'register' ? 'active' : ''}">
        <form id="registration-form">
          <div class="form-row">
            <div>
              <label for="reg-name">Full name</label>
              <input id="reg-name" name="name" type="text" required 
                     placeholder="Enter your full name" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-email">Email address</label>
              <input id="reg-email" name="email" type="email" required 
                     placeholder="Enter your email" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-phone">Phone number</label>
              <input id="reg-phone" name="phone" type="tel" required 
                     placeholder="+1 555 123-4567" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-password">Password</label>
              <input id="reg-password" name="password" type="password" required 
                     placeholder="Create a password" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-confirm-password">Confirm password</label>
              <input id="reg-confirm-password" name="confirmPassword" type="password" required 
                     placeholder="Confirm your password" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <button type="submit" class="btn primary">Create Account</button>
            </div>
          </div>
        </form>
      </div>
      
      <!-- Сообщения об ошибках и успехе -->
      <div id="auth-messages"></div>
    </div>
  `;

  // Вставляем HTML в контейнер
  containerEl.innerHTML = authHTML;

  // Предзаполняем данные, если они предоставлены
  if (prefillData) {
    if (prefillData.email) {
      const emailInput = containerEl.querySelector('#login-email');
      const regEmailInput = containerEl.querySelector('#reg-email');
      if (emailInput) emailInput.value = prefillData.email;
      if (regEmailInput) regEmailInput.value = prefillData.email;
    }
    if (prefillData.name) {
      const nameInput = containerEl.querySelector('#reg-name');
      if (nameInput) nameInput.value = prefillData.name;
    }
    if (prefillData.phone) {
      const phoneInput = containerEl.querySelector('#reg-phone');
      if (phoneInput) phoneInput.value = prefillData.phone;
    }
  }

  // Инициализируем обработчики событий
  const authMenu = {
    container: containerEl,
    switchTab: (tabName) => {
      // Обновляем активную вкладку
      containerEl.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
      });

      // Показываем соответствующую форму
      containerEl.querySelectorAll('.auth-form').forEach(form => {
        form.classList.toggle('active', form.id === `${tabName}-form`);
      });

      // Очищаем сообщения
      authMenu.clearMessages();
    },

    showMessage: (message, type = 'info') => {
      const messagesContainer = containerEl.querySelector('#auth-messages');
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
    },

    clearMessages: () => {
      const messagesContainer = containerEl.querySelector('#auth-messages');
      if (messagesContainer) {
        messagesContainer.innerHTML = '';
      }
    },

    showForgotPasswordForm: () => {
      const loginForm = containerEl.querySelector('#login-form');
      const forgotForm = containerEl.querySelector('#forgot-password-form');
      if (loginForm && forgotForm) {
        loginForm.style.display = 'none';
        forgotForm.style.display = 'block';
        
        // Заполняем email в форме восстановления
        const loginEmail = containerEl.querySelector('#login-email').value;
        const resetEmail = containerEl.querySelector('#reset-email');
        if (resetEmail) {
          resetEmail.value = loginEmail;
        }
      }
    },

    showLoginForm: () => {
      const loginForm = containerEl.querySelector('#login-form');
      const forgotForm = containerEl.querySelector('#forgot-password-form');
      if (loginForm && forgotForm) {
        forgotForm.style.display = 'none';
        loginForm.style.display = 'block';
      }
    }
  };

  // Переключение между вкладками
  containerEl.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      authMenu.switchTab(e.target.dataset.tab);
    });
  });

  // Форма входа
  const loginForm = containerEl.querySelector('#login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = containerEl.querySelector('#login-email').value;
      const password = containerEl.querySelector('#login-password').value;

      if (!email || !password) {
        authMenu.showMessage('Please fill in all fields', 'error');
        return;
      }

      try {
        // Используем AuthSystem, если доступен
        if (window.authSystem) {
          const user = await window.authSystem.findUserByEmail(email);
          
          if (!user) {
            authMenu.showMessage('No account found with this email. Please create an account.', 'info');
            authMenu.switchTab('register');
            containerEl.querySelector('#reg-email').value = email;
            return;
          }

          if (user.password !== password) {
            authMenu.showMessage('Incorrect password. Please try again.', 'error');
            containerEl.querySelector('#login-password').value = '';
            authMenu.showForgotPasswordForm();
            return;
          }

          await window.authSystem.loginUser(user);
          authMenu.showMessage('Successfully signed in!', 'success');
          
          if (onLogin) {
            onLogin(user);
          } else {
            setTimeout(() => {
              window.location.href = 'dashboard.html';
            }, 1000);
          }
        } else {
          // Fallback: если AuthSystem недоступен, вызываем callback
          if (onLogin) {
            onLogin({ email, password });
          } else {
            authMenu.showMessage('Authentication system not available', 'error');
          }
        }
      } catch (error) {
        authMenu.showMessage('Login failed. Please try again.', 'error');
      }
    });
  }

  // Форма регистрации
  const regForm = containerEl.querySelector('#registration-form');
  if (regForm) {
    regForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const name = containerEl.querySelector('#reg-name').value;
      const email = containerEl.querySelector('#reg-email').value;
      const phone = containerEl.querySelector('#reg-phone').value;
      const password = containerEl.querySelector('#reg-password').value;
      const confirmPassword = containerEl.querySelector('#reg-confirm-password').value;

      if (!name || !email || !phone || !password || !confirmPassword) {
        authMenu.showMessage('Please fill in all fields', 'error');
        return;
      }

      if (password !== confirmPassword) {
        authMenu.showMessage('Passwords do not match', 'error');
        return;
      }

      if (password.length < 6) {
        authMenu.showMessage('Password must be at least 6 characters long', 'error');
        return;
      }

      try {
        // Используем AuthSystem, если доступен
        if (window.authSystem) {
          const existingUser = await window.authSystem.findUserByEmail(email);
          
          if (existingUser) {
            authMenu.showMessage('An account with this email already exists. Please sign in.', 'error');
            authMenu.switchTab('signin');
            containerEl.querySelector('#login-email').value = email;
            return;
          }

          const newUser = await window.authSystem.createUser({ name, email, phone, password });
          await window.authSystem.sendConfirmationEmail(email, name);
          
          authMenu.showMessage('Account created successfully! Please check your email for confirmation.', 'success');
          
          if (onRegister) {
            onRegister(newUser);
          } else {
            setTimeout(() => {
              authMenu.switchTab('signin');
              containerEl.querySelector('#login-email').value = email;
              containerEl.querySelector('#login-password').value = '';
            }, 2000);
          }
        } else {
          // Fallback: если AuthSystem недоступен, вызываем callback
          if (onRegister) {
            onRegister({ name, email, phone, password });
          } else {
            authMenu.showMessage('Authentication system not available', 'error');
          }
        }
      } catch (error) {
        authMenu.showMessage('Registration failed. Please try again.', 'error');
      }
    });
  }

  // Форма восстановления пароля
  const forgotForm = containerEl.querySelector('#forgot-password-form');
  if (forgotForm) {
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = containerEl.querySelector('#reset-email').value;

      if (!email) {
        authMenu.showMessage('Please enter your email address', 'error');
        return;
      }

      try {
        // Используем AuthSystem, если доступен
        if (window.authSystem) {
          const user = await window.authSystem.findUserByEmail(email);
          
          if (!user) {
            authMenu.showMessage('No account found with this email address', 'error');
            return;
          }

          await window.authSystem.sendPasswordResetEmail(email);
          authMenu.showMessage('Password reset link sent to your email. Please check your inbox.', 'success');
          
          if (onPasswordReset) {
            onPasswordReset(email);
          } else {
            setTimeout(() => {
              authMenu.showLoginForm();
            }, 2000);
          }
        } else {
          // Fallback: если AuthSystem недоступен, вызываем callback
          if (onPasswordReset) {
            onPasswordReset(email);
          } else {
            authMenu.showMessage('Authentication system not available', 'error');
          }
        }
      } catch (error) {
        authMenu.showMessage('Failed to send reset link. Please try again.', 'error');
      }
    });
  }

  // Ссылки для переключения форм
  const forgotLink = containerEl.querySelector('#forgot-password-link');
  if (forgotLink) {
    forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      authMenu.showForgotPasswordForm();
    });
  }

  const backToLoginLink = containerEl.querySelector('#back-to-login-link');
  if (backToLoginLink) {
    backToLoginLink.addEventListener('click', (e) => {
      e.preventDefault();
      authMenu.showLoginForm();
    });
  }

  return authMenu;
}

// Экспорт для использования в других файлах
window.createAuthMenu = createAuthMenu;

