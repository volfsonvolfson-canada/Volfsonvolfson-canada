# Уведомления об ошибках при бронировании комнат

## Полный список уведомлений и логика их появления

### 1. Уведомление: "Эта дата занята. Пожалуйста, выберите другую дату."
**Тип:** Первое сообщение (красный фон)  
**Файл:** `script.js` (строка 751)  
**Логика появления:**
- **Триггер:** Пользователь выбирает дату заезда (check-in) в календаре Flatpickr, которая находится в списке `blockedDatesArray`
- **Условие:** `if (dateStr && blockedDatesArray.includes(dateStr))`
- **Действия:**
  1. Очищается выбор даты (`instance.clear()`)
  2. Очищается значение реального input (`checkinInput.value = ''`)
  3. Восстанавливается placeholder (`dd.mm.yyyy`)
  4. Показывается уведомление через `showDateErrorNotification(checkinInput, 'Эта дата занята. Пожалуйста, выберите другую дату.')`
  5. Поле мигает красным (`flashDateField(checkinInput)`)
- **Функция:** `onChange` обработчик Flatpickr для check-in (строка 740-753)

---

### 2. Уведомление: "Check-out cannot be earlier than Check-in.\nPlease select a later date."
**Тип:** Первое сообщение (красный фон)  
**Файл:** `script.js` (строка 771)  
**Логика появления:**
- **Триггер:** Пользователь выбирает дату заезда (check-in), которая позже или равна уже выбранной дате выезда (check-out)
- **Условие:** `if (checkinDate && checkoutDate && checkinDate >= checkoutDate)`
- **Действия:**
  1. Показывается уведомление через `showDateErrorNotification(checkinInput, 'Check-out cannot be earlier than Check-in.\nPlease select a later date.', true)` - первый параметр `true` означает красный фон
  2. Поле мигает красным (`flashDateField(checkinInput)`)
- **Функция:** `onChange` обработчик Flatpickr для check-in (строка 765-774)

---

### 3. Уведомление: "Эта дата занята. Пожалуйста, выберите другую дату." (для check-out)
**Тип:** Первое сообщение (красный фон)  
**Файл:** `script.js` (строка 912)  
**Логика появления:**
- **Триггер:** Пользователь выбирает дату выезда (check-out) в календаре Flatpickr, которая находится в списке `disabledDates`
- **Условие:** `if (dateStr && disabledDates.includes(dateStr))`
- **Действия:**
  1. Очищается выбор даты (`instance.clear()`)
  2. Очищается значение реального input (`checkoutInput.value = ''`)
  3. Восстанавливается placeholder (`dd.mm.yyyy`)
  4. Определяется причина блокировки:
     - Если дата ≤ check-in + 1 день: сообщение "Дата выезда должна быть минимум на 2 дня позже даты заезда."
     - Иначе: "Эта дата занята. Пожалуйста, выберите другую дату."
  5. Показывается уведомление через `showDateErrorNotification(checkoutInput, errorMessage)`
  6. Поле мигает красным (`flashDateField(checkoutInput)`)
- **Функция:** `onChange` обработчик Flatpickr для check-out (строка 883-914)

---

### 4. Уведомление: "Дата выезда должна быть минимум на 2 дня позже даты заезда."
**Тип:** Первое сообщение (красный фон)  
**Файл:** `script.js` (строка 907)  
**Логика появления:**
- **Триггер:** Пользователь выбирает дату выезда (check-out), которая меньше или равна check-in + 1 день
- **Условие:** `if (selectedDate <= checkinPlusOne)` где `checkinPlusOne = checkinDate + 1 день`
- **Действия:**
  1. Очищается выбор даты
  2. Показывается уведомление с сообщением "Дата выезда должна быть минимум на 2 дня позже даты заезда."
  3. Поле мигает красным
- **Функция:** `onChange` обработчик Flatpickr для check-out (строка 883-914)

---

### 5. Уведомление: "Эта дата занята. Пожалуйста, выберите другую дату." (через клиентскую валидацию)
**Тип:** Первое сообщение (красный фон)  
**Файл:** `script.js` (строка 958)  
**Логика появления:**
- **Триггер:** Пользователь пытается выбрать дату, которая заблокирована (через клиентскую валидацию в `initBlockedDatesForRoom`)
- **Условие:** Дата находится в списке заблокированных дат
- **Действия:**
  1. Устанавливается `setCustomValidity('This date is not available. Please select another date.')`
  2. Показывается уведомление через `showDateErrorNotification(input, 'Эта дата занята. Пожалуйста, выберите другую дату.')`
  3. Поле мигает красным (`flashDateField(input)`)
- **Функция:** `initBlockedDatesForRoom` (строка 957-961)

---

### 6. Валидация полей формы (при нажатии "Book now")
**Тип:** Второе сообщение (зеленый фон для дат) или `.field-error` (для остальных полей)  
**Файл:** `booking.js` (строка 368)  
**Логика появления:**
- **Триггер:** Пользователь нажимает кнопку "Book now", но форма не прошла валидацию
- **Условие:** `if (!validation.valid)` где `validation = validateBookingForm(formData)`
- **Возможные ошибки:**
  - `room_name`: "Room selection is required"
  - `checkin_date`: 
    - "Check-in date is required"
    - "Invalid check-in date format"
    - "Check-in date cannot be in the past"
  - `checkout_date`:
    - "Check-out date is required"
    - "Invalid check-out date format"
    - "Check-out date must be after check-in date"
  - `guest_name`: "Name is required"
  - `email`: 
    - "Email is required"
    - "Invalid email address"
  - `phone`: "Phone number is required"
  - `guests_count`: "At least 1 guest is required"
  - **Примечание:** Поле `pets` имеет атрибут `required` в HTML, но в JavaScript валидации (`validateBookingForm`) нет проверки для этого поля. Валидация может происходить только через HTML5 валидацию браузера.
- **Действия:**
  1. Вызывается `showFormErrors(form, validation.errors)`
  2. Для полей дат (`checkin_date`, `checkout_date`) используется `showDateErrorNotification` (зеленый фон, так как `isFirstMessage` не передается)
  3. Для остальных полей (`guest_name`, `email`, `phone`, `guests_count`) создается элемент `.field-error`, который вставляется после поля через `field.parentNode?.insertBefore(errorMsg, field.nextSibling)`
  4. К полям добавляется класс `invalid-field` для визуального выделения
- **Функция:** `handleBookingForm` (строка 365-369) → `showFormErrors` (строка 440-478)

---

### 7. Уведомление об отсутствии доступности дат
**Тип:** Второе сообщение (зеленый фон)  
**Файл:** `booking.js` (строка 390)  
**Логика появления:**
- **Триггер:** Пользователь нажимает "Book now", форма прошла валидацию, но даты недоступны на сервере
- **Условие:** `if (!availability.available)` где `availability = await checkAvailability(...)`
- **Сообщение:** `availability.message` (сообщение с сервера)
- **Действия:**
  1. Вызывается `showFormError(form, 'availability', availability.message)`
  2. Создается контейнер `.form-errors` с сообщением об ошибке
- **Функция:** `handleBookingForm` (строка 388-391)

---

### 8. Уведомление об ошибке создания бронирования
**Тип:** Второе сообщение (зеленый фон)  
**Файл:** `booking.js` (строка 401)  
**Логика появления:**
- **Триггер:** Пользователь нажимает "Book now", форма прошла валидацию, даты доступны, но сервер вернул ошибку при создании бронирования
- **Условие:** `if (!result.success)` где `result = await createBooking(formData)`
- **Сообщение:** `result.error || 'Failed to create booking'`
- **Действия:**
  1. Вызывается `showFormError(form, 'submit', result.error || 'Failed to create booking')`
  2. Создается контейнер `.form-errors` с сообщением об ошибке
- **Функция:** `handleBookingForm` (строка 399-402)

---

### 9. Уведомление об общей ошибке
**Тип:** Второе сообщение (зеленый фон)  
**Файл:** `booking.js` (строка 430)  
**Логика появления:**
- **Триггер:** Произошла исключительная ситуация (exception) при обработке формы
- **Условие:** `catch (error)` в блоке try-catch функции `handleBookingForm`
- **Сообщение:** `error.message || 'An error occurred'`
- **Действия:**
  1. Вызывается `showFormError(form, 'submit', error.message || 'An error occurred')`
  2. Создается контейнер `.form-errors` с сообщением об ошибке
- **Функция:** `handleBookingForm` (строка 429-431)

---

### 10. Уведомления HTML5 валидации (встроенные сообщения браузера)
**Тип:** Встроенные сообщения браузера  
**Файл:** Все HTML файлы с формами (`room-basement.html`, `room-second-suite.html`, и т.д.)  
**Логика появления:**
- **Триггер:** Пользователь пытается отправить форму, но поля с атрибутом `required` не заполнены
- **Условие:** HTML5 валидация браузера (атрибут `required` в HTML)
- **Поля с `required`:**
  - `checkin` (check-in date)
  - `checkout` (check-out date)
  - `name` (guest name)
  - `phone` (phone number)
  - `email` (email address)
  - `guests` (guests count)
  - `pets` (pets selection)
- **Действия:**
  1. Браузер показывает встроенное сообщение валидации (обычно всплывающее сообщение рядом с полем)
  2. Форма не отправляется (`preventDefault()`)
- **Примечание:** 
  - В коде `reportValidity()` временно отключен (комментарий "ВРЕМЕННО ОТКЛЮЧЕНО")
  - Однако HTML5 валидация все еще может работать при попытке submit формы, если форма не имеет `preventDefault()` на событии submit
  - В `booking.js` форма обрабатывается через `handleBookingForm`, который вызывает `preventDefault()` и выполняет кастомную валидацию через `validateBookingForm`
  - **Поле `pets`:** Имеет атрибут `required` в HTML, но НЕ проверяется в `validateBookingForm`. Если поле не заполнено, может показаться HTML5 сообщение браузера, но JavaScript валидация его не обработает

---

## Визуальные отличия

### Первое сообщение (красный фон #ff0000)
- Показывается **мгновенно** при выборе некорректной даты в календаре
- Используется функция `showDateErrorNotification` с параметром `isFirstMessage = true`
- Показывается на **4 секунды**
- Позиционируется **сразу под видимым полем ввода** (altInput Flatpickr)

### Второе сообщение (зеленый фон #00ff00)
- Показывается при нажатии кнопки "Book now" если есть ошибки валидации
- Используется функция `showDateErrorNotification` **без** параметра `isFirstMessage` (или `false`)
- Показывается на **4 секунды**
- Позиционируется **сразу под видимым полем ввода** (altInput Flatpickr)

---

## Дополнительные визуальные эффекты

### Мигание поля (flashDateField)
- Применяется красная рамка к полю через класс `flash-invalid`
- Анимация длится 700ms
- Используется для всех полей дат при ошибках первого типа

### Класс invalid-field
- Добавляется к полям формы при ошибках валидации (второй тип)
- Используется для стилизации полей с ошибками

### Элемент .field-error
- Создается для полей, которые НЕ являются датами (`guest_name`, `email`, `phone`, `guests_count`)
- Вставляется после поля через `field.parentNode?.insertBefore(errorMsg, field.nextSibling)`
- Имеет класс `field-error` и ID `error-{fieldName}`
- Содержит текстовое сообщение об ошибке
- **Примечание:** В CSS нет стилей для `.field-error`, возможно, они должны быть добавлены или используются стили по умолчанию

---

## Функции показа уведомлений

### showDateErrorNotification(input, message, isFirstMessage = false)
**Файл:** `script.js` (строка 424)  
**Параметры:**
- `input` - элемент input (оригинальный или altInput Flatpickr)
- `message` - текст сообщения
- `isFirstMessage` - флаг для определения цвета фона (true = красный, false/undefined = зеленый)

**Логика:**
1. Определяет видимое поле ввода (altInput если используется Flatpickr)
2. Использует `ValidationUtils.showBubble` если доступна
3. Устанавливает цвет фона в зависимости от `isFirstMessage`
4. Показывает уведомление на 4 секунды
5. Позиционирует уведомление сразу под полем ввода

### showFormErrors(form, errors)
**Файл:** `booking.js` (строка 440)  
**Параметры:**
- `form` - элемент формы
- `errors` - объект с ошибками валидации

**Логика:**
1. Очищает предыдущие ошибки через `clearFormErrors(form)`
2. Для каждого поля с ошибкой:
   - Находит поле в форме (по `name` или `id`)
   - Добавляет класс `invalid-field` для визуального выделения
   - **Для полей дат** (`checkin_date`, `checkout_date`):
     - Использует `showDateErrorNotification(field, errors[fieldName])` (без `isFirstMessage`, т.е. зеленый фон)
     - Показывает всплывающее уведомление (`.btb-bubble` стиль)
   - **Для остальных полей** (`guest_name`, `email`, `phone`, `guests_count`):
     - Создает элемент `<div class="field-error" id="error-{fieldName}">`
     - Вставляет элемент после поля через `field.parentNode?.insertBefore(errorMsg, field.nextSibling)`
     - Показывает текстовое сообщение об ошибке

**Маппинг полей HTML → JavaScript:**
- HTML: `name` → JavaScript: `guest_name`
- HTML: `guests` → JavaScript: `guests_count`
- HTML: `pets` → JavaScript: `pets` (булево значение: `true` если `value === 'add'`, иначе `false`)

### showFormError(form, fieldName, message)
**Файл:** `booking.js` (строка 486)  
**Параметры:**
- `form` - элемент формы
- `fieldName` - имя поля (например, 'availability', 'submit')
- `message` - текст сообщения

**Логика:**
1. Создает или находит контейнер `.form-errors`
2. Добавляет сообщение об ошибке в контейнер
3. Вставляет контейнер в начало формы

---

## Схема появления уведомлений

```
Пользователь выбирает дату в календаре
    ↓
Проверка: дата заблокирована?
    ├─ ДА → Уведомление #1 (красный) + мигание поля
    └─ НЕТ → Проверка: check-in >= check-out?
        ├─ ДА → Уведомление #2 (красный) + мигание поля
        └─ НЕТ → Дата выбрана успешно

Пользователь нажимает "Book now"
    ↓
Валидация формы
    ├─ Ошибки валидации → 
    │   ├─ Для полей дат: Уведомления #6 (зеленый фон, `.btb-bubble`)
    │   └─ Для остальных полей: Уведомления #6 (`.field-error` элементы)
    └─ Валидация прошла
        ↓
Проверка доступности на сервере
    ├─ Даты недоступны → Уведомление #7 (зеленый)
    └─ Даты доступны
        ↓
Создание бронирования на сервере
    ├─ Ошибка → Уведомление #8 (зеленый)
    └─ Успех → Редирект на страницу подтверждения
```

