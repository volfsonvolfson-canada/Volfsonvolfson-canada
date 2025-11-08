# Система аутентификации Back to Base Hotel

## Описание

Полноценная система аутентификации с использованием базы данных MySQL, хеширования паролей (bcrypt) и JWT токенов.

## Установка

### 1. Создание базы данных

Выполните SQL скрипт для создания таблиц:

```bash
mysql -u your_username -p your_database < database/create_users_table.sql
```

Или выполните SQL скрипт через phpMyAdmin или другой инструмент управления БД.

### 2. Настройка конфигурации

Откройте `config.php` и настройте:

- **JWT_SECRET**: Измените на случайный сильный ключ (минимум 32 символа)
  ```php
  // Генерация ключа: openssl rand -base64 32
  define('JWT_SECRET', 'your-generated-secret-key-here');
  ```

- **База данных**: Убедитесь, что настройки подключения к БД корректны

### 3. HTTPS

Убедитесь, что сайт работает через HTTPS. Это необходимо для безопасности JWT токенов и cookies.

## Структура базы данных

### Таблица `users`

- `id` - Уникальный идентификатор пользователя
- `email` - Email пользователя (уникальный)
- `password_hash` - Хешированный пароль (bcrypt)
- `name` - Имя пользователя
- `phone` - Телефон
- `phone2` - Дополнительный телефон
- `is_verified` - Статус верификации (0/1)
- `created_at` - Дата создания
- `updated_at` - Дата обновления
- `last_session` - Время последнего входа

### Таблица `user_tokens` (опционально)

Для отзыва токенов при выходе из системы.

## API Endpoints

### Регистрация
```
POST api.php
action=register
name=Имя
email=email@example.com
phone=+1234567890
password=password123
```

### Вход
```
POST api.php
action=login
email=email@example.com
password=password123
```

### Проверка токена
```
GET api.php?action=verify
Headers: Authorization: Bearer {token}
```

### Поиск пользователя по email
```
GET api.php?action=find_by_email&email=email@example.com
```

### Обновление профиля
```
POST api.php
action=update_profile
name=Новое имя
phone=+1234567890
Headers: Authorization: Bearer {token}
```

### Изменение пароля
```
POST api.php
action=change_password
current_password=старый_пароль
new_password=новый_пароль
Headers: Authorization: Bearer {token}
```

### Выход
```
POST api.php
action=logout
```

## Безопасность

1. **Пароли**: Хранятся в хешированном виде с использованием bcrypt (cost: 12)
2. **JWT токены**: Подписываются секретным ключом, имеют срок действия (30 дней)
3. **Cookies**: Устанавливаются с флагами `secure` и `httponly`
4. **Валидация**: Все данные валидируются на сервере
5. **Уникальность email**: Гарантируется на уровне базы данных (UNIQUE constraint)

## Использование в JavaScript

```javascript
// Проверка статуса аутентификации
await window.authSystem.checkAuthStatus();

// Регистрация
const user = await window.authSystem.createUser({
  name: 'Имя',
  email: 'email@example.com',
  phone: '+1234567890',
  password: 'password123'
});

// Вход
await window.authSystem.loginUser({
  email: 'email@example.com',
  password: 'password123'
});

// Выход
window.authSystem.logout();
```

## Миграция с localStorage

Старые данные из localStorage (`btb_users`) не будут автоматически перенесены в базу данных. Пользователям нужно будет зарегистрироваться заново.

## Важные замечания

1. **JWT_SECRET**: Обязательно измените на случайный сильный ключ в продакшене
2. **HTTPS**: Обязательно используйте HTTPS для защиты токенов
3. **Резервное копирование**: Регулярно делайте резервные копии базы данных
4. **Мониторинг**: Следите за логами ошибок для выявления проблем

