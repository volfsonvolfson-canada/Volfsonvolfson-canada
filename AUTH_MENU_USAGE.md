# Универсальная функция меню Sign In / Create Account

## Описание

Универсальная функция `createAuthMenu()` создает меню входа и регистрации на основе структуры `login.html`. Может быть использована на любой странице сайта.

## Использование

### Базовое использование

```javascript
// Создать меню в контейнере с id="auth-container"
const authMenu = createAuthMenu('#auth-container');
```

### С опциями

```javascript
const authMenu = createAuthMenu('#auth-container', {
  defaultTab: 'register', // 'signin' или 'register'
  onLogin: (user) => {
    console.log('User logged in:', user);
    // Ваша логика после входа
  },
  onRegister: (user) => {
    console.log('User registered:', user);
    // Ваша логика после регистрации
  },
  onPasswordReset: (email) => {
    console.log('Password reset requested for:', email);
    // Ваша логика после запроса сброса пароля
  }
});
```

### HTML структура

```html
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <!-- Контейнер для меню -->
  <div id="auth-container"></div>
  
  <!-- Подключаем необходимые скрипты -->
  <script src="utils.js"></script>
  <script src="script.js"></script>
  <script src="auth.js"></script>
  <script src="auth-menu.js"></script>
  
  <script>
    // Инициализация меню после загрузки страницы
    document.addEventListener('DOMContentLoaded', () => {
      createAuthMenu('#auth-container');
    });
  </script>
</body>
</html>
```

## Параметры

### `container` (обязательный)
- **Тип:** `string | HTMLElement`
- **Описание:** Селектор CSS или элемент DOM, куда будет вставлено меню

### `options` (опциональный)
- **Тип:** `Object`
- **Свойства:**
  - `defaultTab` (string): Вкладка по умолчанию - `'signin'` или `'register'` (по умолчанию: `'signin'`)
  - `onLogin` (Function): Callback при успешном входе. Получает объект пользователя
  - `onRegister` (Function): Callback при успешной регистрации. Получает объект нового пользователя
  - `onPasswordReset` (Function): Callback при запросе сброса пароля. Получает email

## Возвращаемое значение

Функция возвращает объект `authMenu` с методами:

- `switchTab(tabName)` - Переключить вкладку ('signin' или 'register')
- `showMessage(message, type)` - Показать сообщение (type: 'success', 'error', 'info')
- `clearMessages()` - Очистить все сообщения
- `showForgotPasswordForm()` - Показать форму восстановления пароля
- `showLoginForm()` - Показать форму входа
- `container` - Элемент контейнера

## Интеграция с AuthSystem

Функция автоматически использует `window.authSystem`, если он доступен. Если `AuthSystem` не инициализирован, используются callbacks из опций.

## Примеры использования

### Пример 1: Простое меню на странице

```html
<div id="auth-menu-container"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  createAuthMenu('#auth-menu-container');
});
</script>
```

### Пример 2: Меню с кастомными обработчиками

```javascript
createAuthMenu('#auth-container', {
  defaultTab: 'register',
  onLogin: (user) => {
    // Перенаправить на страницу профиля
    window.location.href = 'dashboard.html';
  },
  onRegister: (user) => {
    // Показать сообщение и переключить на вкладку входа
    alert('Account created! Please sign in.');
    authMenu.switchTab('signin');
  }
});
```

### Пример 3: Меню в модальном окне

```html
<div id="auth-modal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <div id="auth-menu-container"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const authMenu = createAuthMenu('#auth-menu-container', {
    onLogin: (user) => {
      // Закрыть модальное окно после входа
      document.getElementById('auth-modal').style.display = 'none';
      window.location.href = 'dashboard.html';
    }
  });
});
</script>
```

## Требования

- `styles.css` - должен содержать стили для `.auth-container`, `.auth-tabs`, `.auth-form`, `.tab-btn`, `.auth-message`
- `auth.js` - рекомендуется для полной функциональности (опционально, если используются callbacks)

## Зависимости

- `auth.js` (опционально) - для использования `AuthSystem`
- `styles.css` - для стилей меню


