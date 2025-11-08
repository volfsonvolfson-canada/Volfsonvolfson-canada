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
    prefillData = null,
    onReady = null
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
        <form id="login-form" novalidate>
          <div class="form-row">
            <div>
              <label for="login-email">Email address</label>
              <input id="login-email" name="email" type="email" data-required="true"
                     placeholder="Enter your email" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="login-password">Password</label>
              <div class="password-input-wrapper">
                <input id="login-password" name="password" type="password" data-required="true"
                       placeholder="Enter your password" />
                <button type="button" class="password-toggle" aria-label="Show password">
                  <svg class="eye-icon eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-icon eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
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
        <form id="forgot-password-form" style="display: none;" novalidate>
          <div class="form-row">
            <div>
              <label for="reset-email">Email address</label>
              <input id="reset-email" name="email" type="email" data-required="true"
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
        <form id="registration-form" novalidate>
          <div class="form-row">
            <div>
              <label for="reg-name">Full name</label>
              <input id="reg-name" name="name" type="text" data-required="true"
                     placeholder="Enter your full name" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-email">Email address</label>
              <input id="reg-email" name="email" type="email" data-required="true"
                     placeholder="Enter your email" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-phone">Phone number</label>
              <input id="reg-phone" name="phone" type="tel" data-required="true"
                     placeholder="+1 555 123-4567" />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-password">Password</label>
              <div class="password-input-wrapper">
                <input id="reg-password" name="password" type="password" data-required="true"
                       placeholder="Create a password" />
                <button type="button" class="password-toggle" aria-label="Show password">
                  <svg class="eye-icon eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-icon eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="reg-confirm-password">Confirm password</label>
              <div class="password-input-wrapper">
                <input id="reg-confirm-password" name="confirmPassword" type="password" data-required="true"
                       placeholder="Confirm your password" />
                <button type="button" class="password-toggle" aria-label="Show password">
                  <svg class="eye-icon eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-icon eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
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

  // Функция для очистки ошибок полей в формах авторизации
  const clearAuthFormErrors = (form) => {
    if (!form) return;
    form.querySelectorAll('.field-error').forEach(error => error.remove());
    form.querySelectorAll('.invalid-field').forEach(field => field.classList.remove('invalid-field'));
  };

  // Функция для показа ошибки поля в формах авторизации
  const showAuthFieldError = (input, message) => {
    if (!input || !message) return;
    
    // Используем глобальную функцию showFieldError, если доступна
    if (window.showFieldError) {
      // Заменяем \n на <br> для отображения в две строки
      const messageWithBreaks = message.replace(/\n/g, '<br>');
      
      // Создаем временный элемент для получения текста с HTML
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = messageWithBreaks;
      
      // Получаем видимое поле
      let visibleField = input;
      if (typeof flatpickr !== 'undefined' && input._flatpickr) {
        const fpInstance = input._flatpickr;
        if (fpInstance.altInput) {
          visibleField = fpInstance.altInput;
        }
      }
      
      // Удаляем предыдущую ошибку, если есть
      const errorId = `error-${input.id || input.name || 'field'}`;
      const existingError = visibleField.parentNode?.querySelector(`#${errorId}`);
      if (existingError) {
        existingError.remove();
      }
      
      // Добавляем класс invalid-field
      visibleField.classList.add('invalid-field');
      if (input !== visibleField) {
        input.classList.add('invalid-field');
      }
      
      // Создаем элемент ошибки с HTML для поддержки переносов строк
      const errorMsg = document.createElement('div');
      errorMsg.className = 'field-error';
      errorMsg.innerHTML = messageWithBreaks;
      errorMsg.id = errorId;
      
      // Вставляем ошибку после видимого поля
      if (visibleField.parentNode) {
        visibleField.parentNode.insertBefore(errorMsg, visibleField.nextSibling);
      }
      
      if (window.flashDateField) {
        window.flashDateField(input);
      }
    } else {
      // Fallback: создаем элемент ошибки вручную
      const errorId = `error-${input.id || input.name || 'field'}`;
      const existingError = input.parentNode?.querySelector(`#${errorId}`);
      if (existingError) {
        existingError.remove();
      }
      
      input.classList.add('invalid-field');
      
      const errorMsg = document.createElement('div');
      errorMsg.className = 'field-error';
      // Заменяем \n на <br> для отображения в две строки
      errorMsg.innerHTML = message.replace(/\n/g, '<br>');
      errorMsg.id = errorId;
      input.parentNode?.insertBefore(errorMsg, input.nextSibling);
    }
  };

  // Функция для показа/скрытия пароля
  const initPasswordToggles = () => {
    containerEl.querySelectorAll('.password-toggle').forEach(toggleBtn => {
      toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation(); // Предотвращаем всплытие события
        const wrapper = toggleBtn.closest('.password-input-wrapper');
        const input = wrapper?.querySelector('input[type="password"], input[type="text"]');
        const eyeOpen = toggleBtn.querySelector('.eye-open');
        const eyeClosed = toggleBtn.querySelector('.eye-closed');
        
        if (input && eyeOpen && eyeClosed) {
          if (input.type === 'password') {
            input.type = 'text';
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = 'block';
            toggleBtn.setAttribute('aria-label', 'Hide password');
          } else {
            input.type = 'password';
            eyeOpen.style.display = 'block';
            eyeClosed.style.display = 'none';
            toggleBtn.setAttribute('aria-label', 'Show password');
          }
        }
      });
      
      // Предотвращаем фокус на input при клике на кнопку
      toggleBtn.addEventListener('mousedown', (e) => {
        e.preventDefault();
      });
    });
  };

  // Инициализируем переключатели пароля
  initPasswordToggles();

  // Проверка совпадения паролей в реальном времени для формы Create Account
  const initPasswordMatchCheck = () => {
    const passwordInput = containerEl.querySelector('#reg-password');
    const confirmPasswordInput = containerEl.querySelector('#reg-confirm-password');
    
    if (passwordInput && confirmPasswordInput) {
      const checkPasswordMatch = () => {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        // Очищаем предыдущую ошибку совпадения, если она была
        const existingError = confirmPasswordInput.parentNode?.querySelector('#error-reg-confirm-password');
        if (existingError && existingError.textContent === 'Passwords do not match') {
          existingError.remove();
          confirmPasswordInput.classList.remove('invalid-field');
        }
        
        // Проверяем совпадение только если оба поля заполнены
        if (password && confirmPassword && password !== confirmPassword) {
          showAuthFieldError(confirmPasswordInput, 'Passwords do not match');
        }
      };
      
      // Проверяем при вводе в поле подтверждения пароля
      confirmPasswordInput.addEventListener('input', checkPasswordMatch);
      
      // Также проверяем при изменении основного пароля
      passwordInput.addEventListener('input', checkPasswordMatch);
    }
  };

  // Инициализируем проверку совпадения паролей
  initPasswordMatchCheck();

  // Вызываем onReady колбэк после создания формы, если он предоставлен
  if (onReady && typeof onReady === 'function') {
    // Используем setTimeout для гарантии, что DOM обновлен
    setTimeout(() => {
      onReady();
    }, 0);
  }

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
      
      // Очищаем предыдущие ошибки
      clearAuthFormErrors(loginForm);
      
      const emailInput = containerEl.querySelector('#login-email');
      const passwordInput = containerEl.querySelector('#login-password');
      const email = emailInput?.value.trim();
      const password = passwordInput?.value;

      let hasErrors = false;

      // Валидация email
      if (!email) {
        showAuthFieldError(emailInput, 'Email address is required');
        hasErrors = true;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAuthFieldError(emailInput, 'Invalid email address');
        hasErrors = true;
      }

      // Валидация password
      if (!password) {
        showAuthFieldError(passwordInput, 'Password is required');
        hasErrors = true;
      }

      if (hasErrors) {
        return;
      }

      try {
        // Используем AuthSystem, если доступен
        if (window.authSystem) {
          // Вход через API (проверка пароля происходит на сервере)
          await window.authSystem.loginUser({ email, password });
          
          // Получаем текущего пользователя из AuthSystem
          const user = window.authSystem.currentUser;
          
          if (onLogin) {
            onLogin(user);
          } else {
            // Если колбэк не предоставлен, показываем сообщение
            authMenu.showMessage('Successfully signed in!', 'success');
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
      
      // Очищаем предыдущие ошибки
      clearAuthFormErrors(regForm);
      
      const nameInput = containerEl.querySelector('#reg-name');
      const emailInput = containerEl.querySelector('#reg-email');
      const phoneInput = containerEl.querySelector('#reg-phone');
      const passwordInput = containerEl.querySelector('#reg-password');
      const confirmPasswordInput = containerEl.querySelector('#reg-confirm-password');
      
      const name = nameInput?.value.trim();
      const email = emailInput?.value.trim();
      const phone = phoneInput?.value.trim();
      const password = passwordInput?.value;
      const confirmPassword = confirmPasswordInput?.value;

      let hasErrors = false;

      // Валидация name
      if (!name) {
        showAuthFieldError(nameInput, 'Full name is required');
        hasErrors = true;
      }

      // Валидация email
      if (!email) {
        showAuthFieldError(emailInput, 'Email address is required');
        hasErrors = true;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAuthFieldError(emailInput, 'Invalid email address');
        hasErrors = true;
      }

      // Валидация phone
      if (!phone) {
        showAuthFieldError(phoneInput, 'Phone number is required');
        hasErrors = true;
      } else {
        // Проверяем формат телефона (минимум 10 цифр)
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(phone)) {
          showAuthFieldError(phoneInput, 'Invalid phone number');
          hasErrors = true;
        }
      }

      // Валидация password
      if (!password) {
        showAuthFieldError(passwordInput, 'Password is required');
        hasErrors = true;
      } else if (password.length < 6) {
        showAuthFieldError(passwordInput, 'Password must be at least 6 characters long');
        hasErrors = true;
      }

      // Валидация confirmPassword
      if (!confirmPassword) {
        showAuthFieldError(confirmPasswordInput, 'Please confirm your password');
        hasErrors = true;
      } else if (password !== confirmPassword) {
        showAuthFieldError(confirmPasswordInput, 'Passwords do not match');
        hasErrors = true;
      }

      if (hasErrors) {
        return;
      }

      try {
        // Используем AuthSystem, если доступен
        if (window.authSystem) {
          const existingUser = await window.authSystem.findUserByEmail(email);
          
          if (existingUser) {
            // Показываем ошибку под полем email (в две строки)
            showAuthFieldError(emailInput, 'An account with this email already exists.\nPlease sign in.');
            
            // Мигаем вкладкой Sign In красным несколько раз
            const signInTab = containerEl.querySelector('.tab-btn[data-tab="signin"]');
            if (signInTab) {
              // Функция для мигания вкладки
              const flashTab = () => {
                signInTab.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                signInTab.style.backgroundColor = 'rgba(255, 77, 77, 0.2)';
                signInTab.style.color = '#ff4d4d';
                
                setTimeout(() => {
                  signInTab.style.backgroundColor = '';
                  signInTab.style.color = '';
                }, 300);
              };
              
              // Мигаем 3 раза с интервалом
              flashTab();
              setTimeout(() => flashTab(), 600);
              setTimeout(() => flashTab(), 1200);
            }
            
            // НЕ переключаем вкладку и НЕ перекидываем пользователя
            return;
          }

          const newUser = await window.authSystem.createUser({ name, email, phone, password });
          
          // Автоматически логиним пользователя после регистрации
          await window.authSystem.loginUser({ email, password });
          
          // Получаем текущего пользователя из AuthSystem
          const loggedInUser = window.authSystem.currentUser;
          
          if (onRegister) {
            onRegister(loggedInUser);
          } else {
            // Если колбэк не предоставлен, показываем сообщение и переключаемся на форму входа
            authMenu.showMessage('Account created successfully!', 'success');
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
      
      // Очищаем предыдущие ошибки
      clearAuthFormErrors(forgotForm);
      
      const emailInput = containerEl.querySelector('#reset-email');
      const email = emailInput?.value.trim();

      let hasErrors = false;

      // Валидация email
      if (!email) {
        showAuthFieldError(emailInput, 'Email address is required');
        hasErrors = true;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAuthFieldError(emailInput, 'Invalid email address');
        hasErrors = true;
      }

      if (hasErrors) {
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

