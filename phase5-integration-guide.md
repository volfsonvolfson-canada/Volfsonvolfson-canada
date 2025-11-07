# Phase 5 Integration Guide
# Руководство по интеграции Phase 5 (Frontend Booking Flow)

## Файлы для обновления страниц комнат

### Шаг 1: Добавить booking.js на страницы комнат

Добавьте следующий скрипт **ПОСЛЕ** `script.js` на всех страницах комнат:

**Файлы для обновления:**
- `room-basement.html`
- `room-first-double.html`
- `room-first-twin.html`
- `room-second-suite.html`
- И любые другие страницы с формами бронирования

**Добавить перед закрывающим `</body>`:**
```html
<script src="script.js"></script>
<script src="booking.js"></script>
```

### Шаг 2: Добавить availability-calendar.js (Опционально)

Если хотите показывать календарь доступности на страницах комнат, добавьте:

```html
<script src="availability-calendar.js"></script>
```

И добавьте контейнер для календаря в HTML страницы (например, перед формой бронирования):

```html
<div id="availability-calendar"></div>
```

Затем инициализируйте календарь в скрипте страницы:

```javascript
document.addEventListener('DOMContentLoaded', () => {
  // Замените 'Basement — Queen' на название комнаты для конкретной страницы
  if (window.AvailabilityCalendar) {
    window.AvailabilityCalendar.createAvailabilityCalendar(
      '#availability-calendar',
      'Basement — Queen' // или другое название комнаты
    );
  }
});
```

### Шаг 3: Проверка работы

После добавления скриптов:
1. Откройте страницу комнаты
2. Заполните форму бронирования
3. Нажмите "Book now"
4. Должен произойти редирект на `booking-confirmation.html`
5. На странице подтверждения должны отобразиться детали бронирования

### Важно

1. **booking.js** должен быть загружен **ПОСЛЕ** `script.js`
2. Формы бронирования автоматически будут использовать новый API вместо localStorage
3. Если `booking.js` не загружен, формы вернутся к старому поведению (localStorage) как fallback

## Что было реализовано

✅ **booking.js** - Модуль для обработки форм бронирования и интеграции с API
✅ **payment.js** - Интеграция Stripe Elements для обработки платежей
✅ **booking-confirmation.html** - Страница подтверждения бронирования
✅ **availability-calendar.js** - Виджет календаря доступности
✅ **script.js** (обновлен) - Формы бронирования теперь используют API вместо localStorage

## Пример добавления на страницу комнаты

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- ... head content ... -->
</head>
<body>
  <!-- ... page content ... -->
  
  <!-- Форма бронирования -->
  <form class="booking-form" data-room="Basement — Queen" data-custom-handler>
    <!-- ... форма ... -->
  </form>
  
  <!-- ... остальной контент ... -->
  
  <script src="script.js"></script>
  <script src="booking.js"></script>
  <!-- Опционально: -->
  <script src="availability-calendar.js"></script>
</body>
</html>
```

## Файлы готовы к загрузке на хостинг

Все файлы готовы:
- ✅ `booking.js`
- ✅ `payment.js`
- ✅ `booking-confirmation.html`
- ✅ `availability-calendar.js`
- ✅ `script.js` (обновлен)

После загрузки на хостинг формы бронирования будут работать с API вместо localStorage.



