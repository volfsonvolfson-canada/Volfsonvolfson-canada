/**
 * Booking.js
 * Обработка форм бронирования и интеграция с API
 */

// Конфигурация API
const BOOKING_API_URL = 'api.php';

// parseLocalDate - парсинг даты YYYY-MM-DD как локальной даты (без часового пояса)
// Используется для избежания проблем с часовыми поясами
function parseLocalDate(iso) {
  if (!iso) return null;
  const parts = String(iso).split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10) - 1;
  const d = parseInt(parts[2], 10);
  if (isNaN(y) || isNaN(m) || isNaN(d)) return null;
  return new Date(y, m, d);
}

/**
 * Проверка доступности дат для комнаты
 * @param {string} roomName Название комнаты
 * @param {string} checkinDate Дата заезда (YYYY-MM-DD)
 * @param {string} checkoutDate Дата выезда (YYYY-MM-DD)
 * @returns {Promise<{available: boolean, message: string}>}
 */
async function checkAvailability(roomName, checkinDate, checkoutDate) {
  try {
    const formData = new FormData();
    formData.append('action', 'check_availability');
    formData.append('room_name', roomName);
    formData.append('checkin_date', checkinDate);
    formData.append('checkout_date', checkoutDate);

    console.log('BookingAPI.checkAvailability: Sending request to:', BOOKING_API_URL);
    console.log('BookingAPI.checkAvailability: Request data:', {
      room_name: roomName,
      checkin_date: checkinDate,
      checkout_date: checkoutDate
    });

    const response = await fetch(BOOKING_API_URL, {
      method: 'POST',
      body: formData
    });

    console.log('BookingAPI.checkAvailability: Response status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('BookingAPI.checkAvailability: HTTP error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    console.log('BookingAPI.checkAvailability: API response:', result);

    if (!result.success) {
      console.error('BookingAPI.checkAvailability: API returned error:', result.error);
      return {
        available: false,
        message: result.error || 'Failed to check availability'
      };
    }

    const availability = {
      available: result.data?.available ?? false,
      message: result.data?.message || (result.data?.available ? 'Dates are available' : 'Dates are not available')
    };
    
    console.log('BookingAPI.checkAvailability: Final availability result:', availability);
    return availability;
  } catch (error) {
    console.error('Check availability error:', error);
    return {
      available: false,
      message: error.message || 'Failed to check availability'
    };
  }
}

/**
 * Создание бронирования
 * @param {Object} bookingData Данные бронирования
 * @returns {Promise<{success: boolean, booking?: Object, error?: string, payment_intent_id?: string, client_secret?: string}>}
 */
async function createBooking(bookingData) {
  try {
    console.log('BookingAPI.createBooking: Starting booking creation with data:', bookingData);
    
    const formData = new FormData();
    formData.append('action', 'create_booking');
    formData.append('room_name', bookingData.room_name || '');
    formData.append('checkin_date', bookingData.checkin_date || '');
    formData.append('checkout_date', bookingData.checkout_date || '');
    formData.append('guest_name', bookingData.guest_name || '');
    formData.append('email', bookingData.email || '');
    formData.append('phone', bookingData.phone || '');
    formData.append('guests_count', bookingData.guests_count || 1);
    formData.append('pets', bookingData.pets ? '1' : '0');
    if (bookingData.special_requests) {
      formData.append('special_requests', bookingData.special_requests);
    }

    console.log('BookingAPI.createBooking: Sending request to:', BOOKING_API_URL);

    // Показываем индикатор загрузки
    showLoadingState();

    const response = await fetch(BOOKING_API_URL, {
      method: 'POST',
      body: formData
    });

    console.log('BookingAPI.createBooking: Response status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('BookingAPI.createBooking: HTTP error response:', errorText);
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    console.log('BookingAPI.createBooking: API response:', result);

    hideLoadingState();

    if (!result.success) {
      return {
        success: false,
        error: result.error || 'Failed to create booking'
      };
    }

    return {
      success: true,
      booking: result.data?.booking,
      booking_id: result.data?.booking_id,
      confirmation_code: result.data?.confirmation_code,
      payment_intent_id: result.data?.payment_intent_id,
      client_secret: result.data?.client_secret,
      payment_required: result.data?.payment_required || false,
      message: result.data?.message || 'Booking created successfully'
    };
  } catch (error) {
    console.error('Create booking error:', error);
    hideLoadingState();
    return {
      success: false,
      error: error.message || 'Failed to create booking'
    };
  }
}

/**
 * Получение бронирования по ID или коду подтверждения
 * @param {number|string} bookingIdOrCode ID бронирования или код подтверждения
 * @returns {Promise<{success: boolean, booking?: Object, error?: string}>}
 */
async function getBooking(bookingIdOrCode) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_booking');
    
    if (typeof bookingIdOrCode === 'number') {
      formData.append('booking_id', bookingIdOrCode.toString());
    } else {
      formData.append('confirmation_code', bookingIdOrCode);
    }

    const response = await fetch(BOOKING_API_URL, {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (!result.success) {
      return {
        success: false,
        error: result.error || 'Booking not found'
      };
    }

    return {
      success: true,
      booking: result.data?.booking
    };
  } catch (error) {
    console.error('Get booking error:', error);
    return {
      success: false,
      error: error.message || 'Failed to get booking'
    };
  }
}

/**
 * Валидация данных формы бронирования
 * @param {Object} formData Данные формы
 * @returns {Object} {valid: boolean, errors: Object}
 */
function validateBookingForm(formData) {
  const errors = {};

  // Валидация в правильной последовательности:
  // 1. Сначала check-in (въезд)
  if (!formData.checkin_date || !formData.checkin_date.trim()) {
    errors.checkin_date = 'Check-in date is required';
  }

  // 2. Потом check-out (выезд)
  if (!formData.checkout_date || !formData.checkout_date.trim()) {
    errors.checkout_date = 'Check-out date is required';
  }

  // 3. Проверка порядка дат (только если обе даты заполнены)
  if (formData.checkin_date && formData.checkout_date) {
    // ИСПРАВЛЕНО: Используем parseLocalDate для правильной обработки дат без часового пояса
    // parseLocalDate парсит YYYY-MM-DD как локальную дату (без времени)
    const checkin = parseLocalDate(formData.checkin_date);
    const checkout = parseLocalDate(formData.checkout_date);
    
    if (!checkin || !checkout) {
      if (!checkin) errors.checkin_date = 'Invalid check-in date format';
      if (!checkout) errors.checkout_date = 'Invalid check-out date format';
    } else {
      // Сравниваем только даты (без времени)
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const checkinDateOnly = new Date(checkin.getFullYear(), checkin.getMonth(), checkin.getDate());
      const checkoutDateOnly = new Date(checkout.getFullYear(), checkout.getMonth(), checkout.getDate());
      const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      
      if (checkinDateOnly < todayDateOnly) {
        errors.checkin_date = 'Check-in date cannot be in the past';
      }

      if (checkoutDateOnly <= checkinDateOnly) {
        errors.checkout_date = 'Check-out date must be after check-in date';
      }
    }
  }

  // 4. Потом name (имя)
  if (!formData.guest_name || !formData.guest_name.trim()) {
    errors.guest_name = 'Name is required';
  }

  // 5. Потом phone (телефон)
  if (!formData.phone || !formData.phone.trim()) {
    errors.phone = 'Phone number is required';
  } else {
    // Проверяем формат телефона (минимум 10 цифр)
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(formData.phone.trim())) {
      errors.phone = 'Invalid phone number';
    }
  }

  // 6. Потом email (почта)
  if (!formData.email || !formData.email.trim()) {
    errors.email = 'Email is required';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Invalid email address';
  }

  // 7. Потом guests (количество гостей)
  if (!formData.guests_count || formData.guests_count < 1) {
    errors.guests_count = 'At least 1 guest is required';
  }

  // 8. Потом pets (наличие питомцев)
  // Примечание: pets может быть boolean или string, проверка не требуется, так как это опциональное поле

  // Проверка комнаты (не критично для последовательности, но оставляем)
  if (!formData.room_name || !formData.room_name.trim()) {
    errors.room_name = 'Room selection is required';
  }

  return {
    valid: Object.keys(errors).length === 0,
    errors
  };
}

/**
 * Обработка формы бронирования
 * @param {HTMLFormElement} form Элемент формы
 * @returns {Promise<boolean>} Успешность обработки
 */
async function handleBookingForm(form) {
  try {
    console.log('BookingAPI.handleBookingForm: Starting...');
    console.log('BookingAPI.handleBookingForm: Form element:', form);
    
    // Собираем данные формы
    // Получаем название комнаты из атрибута формы или скрытого поля
    let roomName = form.querySelector('[name="room_name"]')?.value || '';
    if (!roomName && form.getAttribute('data-room')) {
      roomName = form.getAttribute('data-room');
    }
    console.log('BookingAPI.handleBookingForm: Room name:', roomName);
    
    // Получаем даты - проверяем разные возможные селекторы
    // Сначала пробуем получить значения напрямую из inputs
    let checkinEl = form.querySelector('#checkin') || form.querySelector('[name="checkin"]') || form.querySelector('[name="checkin_date"]');
    let checkoutEl = form.querySelector('#checkout') || form.querySelector('[name="checkout"]') || form.querySelector('[name="checkout_date"]');
    
    // Если используется Flatpickr, синхронизируем значения перед чтением
    if (typeof flatpickr !== 'undefined' && checkinEl) {
      try {
        const checkinFp = checkinEl._flatpickr || (checkinEl.dataset.flatpickrInitialized ? flatpickr(checkinEl, {}) : null);
        if (checkinFp) {
          if (checkinFp.selectedDates && checkinFp.selectedDates.length > 0) {
            const dateStr = checkinFp.formatDate(checkinFp.selectedDates[0], 'Y-m-d');
            if (dateStr) {
              checkinEl.value = dateStr;
              checkinEl.type = 'date';
            }
          } else if (checkinFp.input && checkinFp.input.value) {
            checkinEl.value = checkinFp.input.value;
            checkinEl.type = 'date';
          }
        }
      } catch (e) {
        console.warn('Could not sync checkin from Flatpickr:', e);
      }
    }
    
    if (typeof flatpickr !== 'undefined' && checkoutEl) {
      try {
        const checkoutFp = checkoutEl._flatpickr || (checkoutEl.dataset.flatpickrInitialized ? flatpickr(checkoutEl, {}) : null);
        if (checkoutFp) {
          if (checkoutFp.selectedDates && checkoutFp.selectedDates.length > 0) {
            const dateStr = checkoutFp.formatDate(checkoutFp.selectedDates[0], 'Y-m-d');
            if (dateStr) {
              checkoutEl.value = dateStr;
              checkoutEl.type = 'date';
            }
          } else if (checkoutFp.input && checkoutFp.input.value) {
            checkoutEl.value = checkoutFp.input.value;
            checkoutEl.type = 'date';
          }
        }
      } catch (e) {
        console.warn('Could not sync checkout from Flatpickr:', e);
      }
    }
    
    let checkinDate = checkinEl?.value || '';
    let checkoutDate = checkoutEl?.value || '';
    
    console.log('BookingAPI.handleBookingForm: Check-in date:', checkinDate);
    console.log('BookingAPI.handleBookingForm: Check-out date:', checkoutDate);
    
    const formData = {
      room_name: roomName,
      checkin_date: checkinDate,
      checkout_date: checkoutDate,
      guest_name: form.querySelector('[name="guest_name"]')?.value || form.querySelector('[name="name"]')?.value || form.querySelector('#name')?.value || '',
      email: form.querySelector('[name="email"]')?.value || form.querySelector('#email')?.value || '',
      phone: form.querySelector('[name="phone"]')?.value || form.querySelector('#phone')?.value || '',
      guests_count: parseInt(form.querySelector('[name="guests_count"]')?.value || form.querySelector('[name="guests"]')?.value || form.querySelector('#guests')?.value || '1', 10),
      pets: form.querySelector('[name="pets"]')?.value === 'add' || form.querySelector('[name="pets"]')?.checked || (form.querySelector('#pets')?.value === 'add'),
      special_requests: form.querySelector('[name="special_requests"]')?.value || ''
    };
    
    console.log('BookingAPI.handleBookingForm: Collected form data:', formData);

    // HTML5 валидация - для обычных полей используем .field-error элементы
    // (не используем reportValidity(), так как он показывает .btb-bubble, а не .field-error)
    // Валидация будет выполнена через validateBookingForm ниже

    // Валидация
    console.log('BookingAPI: Validating form data...', formData);
    const validation = validateBookingForm(formData);
    console.log('BookingAPI: Validation result:', validation);
    if (!validation.valid) {
      console.error('BookingAPI: Validation failed:', validation.errors);
      
      // Находим первое поле с ошибкой в правильной последовательности:
      // 1. check-in, 2. check-out, 3. name, 4. остальные
      const checkinEl = form.querySelector('#checkin') || form.querySelector('[name="checkin"]') || form.querySelector('[name="checkin_date"]');
      const checkoutEl = form.querySelector('#checkout') || form.querySelector('[name="checkout"]') || form.querySelector('[name="checkout_date"]');
      const nameEl = form.querySelector('#name') || form.querySelector('[name="name"]') || form.querySelector('[name="guest_name"]');
      
      // 1. Сначала проверяем check-in
      if (validation.errors.checkin_date && checkinEl) {
        // Для полей дат используем .field-error элементы (как для обычных полей)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkinEl, validation.errors.checkin_date);
        }
        if (window.flashDateField) {
          window.flashDateField(checkinEl);
        }
        checkinEl.focus();
        return false;
      }
      
      // 2. Потом check-out
      if (validation.errors.checkout_date && checkoutEl) {
        // Для полей дат используем .field-error элементы (как для обычных полей)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkoutEl, validation.errors.checkout_date);
        }
        if (window.flashDateField) {
          if (checkinEl) window.flashDateField(checkinEl);
          window.flashDateField(checkoutEl);
        }
        checkoutEl.focus();
        return false;
      }
      
      // 3. Потом name (имя)
      if (validation.errors.guest_name && nameEl) {
        // Используем .field-error элементы (как для полей дат)
        if (window.showFieldError) {
          window.showFieldError(nameEl, validation.errors.guest_name);
        }
        if (window.flashDateField) {
          window.flashDateField(nameEl);
        }
        nameEl.focus();
        return false;
      }
      
      // 4. Потом phone (телефон)
      const phoneEl = form.querySelector('#phone') || form.querySelector('[name="phone"]');
      if (validation.errors.phone && phoneEl) {
        if (window.showFieldError) {
          window.showFieldError(phoneEl, validation.errors.phone);
        }
        if (window.flashDateField) {
          window.flashDateField(phoneEl);
        }
        phoneEl.focus();
        return false;
      }
      
      // 5. Потом email (почта)
      const emailEl = form.querySelector('#email') || form.querySelector('[name="email"]');
      if (validation.errors.email && emailEl) {
        if (window.showFieldError) {
          window.showFieldError(emailEl, validation.errors.email);
        }
        if (window.flashDateField) {
          window.flashDateField(emailEl);
        }
        emailEl.focus();
        return false;
      }
      
      // 6. Потом guests (количество гостей)
      const guestsEl = form.querySelector('#guests') || form.querySelector('[name="guests"]') || form.querySelector('[name="guests_count"]');
      if (validation.errors.guests_count && guestsEl) {
        if (window.showFieldError) {
          window.showFieldError(guestsEl, validation.errors.guests_count);
        }
        if (window.flashDateField) {
          window.flashDateField(guestsEl);
        }
        guestsEl.focus();
        return false;
      }
      
      // 7. Потом pets (наличие питомцев)
      const petsEl = form.querySelector('#pets') || form.querySelector('[name="pets"]');
      if (validation.errors.pets && petsEl) {
        if (window.showFieldError) {
          window.showFieldError(petsEl, validation.errors.pets);
        }
        if (window.flashDateField) {
          window.flashDateField(petsEl);
        }
        petsEl.focus();
        return false;
      }
      
      // 8. Потом остальные поля - показываем первую ошибку
      const firstErrorField = Object.keys(validation.errors)[0];
      if (firstErrorField) {
        const field = form.querySelector(`[name="${firstErrorField}"]`) || form.querySelector(`#${firstErrorField}`);
        if (field && window.showFieldError) {
          window.showFieldError(field, validation.errors[firstErrorField]);
          if (window.flashDateField) {
            window.flashDateField(field);
          }
          field.focus();
        }
      }
      
      return false;
    }

    // Очищаем предыдущие ошибки
    clearFormErrors(form);

    // Проверяем доступность
    console.log('BookingAPI: Checking availability...', {
      room: formData.room_name,
      checkin: formData.checkin_date,
      checkout: formData.checkout_date
    });
    const availability = await checkAvailability(
      formData.room_name,
      formData.checkin_date,
      formData.checkout_date
    );
    console.log('BookingAPI: Availability result:', availability);

    if (!availability.available) {
      console.error('BookingAPI: Dates not available:', availability.message);
      showFormError(form, 'availability', availability.message);
      return false;
    }

    // Создаем бронирование
    console.log('BookingAPI: Creating booking with data:', formData);
    const result = await createBooking(formData);
    console.log('BookingAPI: Booking creation result:', result);

    if (!result.success) {
      console.error('BookingAPI: Booking creation failed:', result.error);
      showFormError(form, 'submit', result.error || 'Failed to create booking');
      return false;
    }

    // Успешное создание бронирования
    // Сохраняем данные бронирования в sessionStorage для страницы подтверждения
    if (result.booking_id) {
      sessionStorage.setItem('last_booking_id', result.booking_id.toString());
      sessionStorage.setItem('last_confirmation_code', result.confirmation_code || '');
      
      // Если требуется оплата, сохраняем данные платежа
      if (result.payment_required && result.client_secret) {
        sessionStorage.setItem('payment_intent_id', result.payment_intent_id || '');
        sessionStorage.setItem('client_secret', result.client_secret);
      }
      
      // Сохраняем бронирование в localStorage для отображения значка домика и редактирования
      try {
        const orders = JSON.parse(localStorage.getItem('btb_orders') || '[]');
        const bookingOrder = {
          id: `booking_${result.booking_id}`,
          kind: 'room',
          room: formData.room_name,
          checkin: formData.checkin_date,
          checkout: formData.checkout_date,
          guests: formData.guests_count,
          pets: formData.pets === 'add' ? 'add' : 'no',
          name: formData.guest_name,
          email: formData.email,
          phone: formData.phone,
          booking_id: result.booking_id,
          confirmation_code: result.confirmation_code,
          status: 'pending',
          ts: Date.now()
        };
        orders.push(bookingOrder);
        localStorage.setItem('btb_orders', JSON.stringify(orders));
        
        // Создаем событие для обновления значка домика
        document.dispatchEvent(new CustomEvent('btb:order:record', { detail: bookingOrder }));
        
        console.log('BookingAPI: Booking saved to localStorage for editing');
      } catch (error) {
        console.error('BookingAPI: Failed to save booking to localStorage:', error);
        // Не прерываем процесс, если не удалось сохранить в localStorage
      }
    }

    // Показываем сообщение об успешном бронировании на той же странице
    console.log('BookingAPI: Booking created successfully');
    console.log('BookingAPI: Booking ID:', result.booking_id);
    console.log('BookingAPI: Confirmation code:', result.confirmation_code);
    
    // Проверяем статус авторизации
    const isAuthenticated = window.authSystem && window.authSystem.isAuthenticated;
    
    // Показываем сообщение об успешном бронировании
    showBookingSuccessMessage(form, {
      isAuthenticated: isAuthenticated,
      bookingData: {
        name: formData.guest_name,
        email: formData.email,
        phone: formData.phone
      }
    });

    return true;
  } catch (error) {
    console.error('BookingAPI.handleBookingForm: Exception caught:', error);
    console.error('BookingAPI.handleBookingForm: Error stack:', error.stack);
    showFormError(form, 'submit', error.message || 'An error occurred');
    return false;
  }
}

/**
 * Показать ошибки формы
 * @param {HTMLFormElement} form Элемент формы
 * @param {Object} errors Объект с ошибками
 */
function showFormErrors(form, errors) {
  // Очищаем предыдущие ошибки
  clearFormErrors(form);

  // Показываем ошибки для каждого поля
  Object.keys(errors).forEach(fieldName => {
    const field = form.querySelector(`[name="${fieldName}"]`) || 
                  form.querySelector(`#${fieldName}`) ||
                  (fieldName === 'checkin_date' ? form.querySelector('#checkin') || form.querySelector('[name="checkin"]') : null) ||
                  (fieldName === 'checkout_date' ? form.querySelector('#checkout') || form.querySelector('[name="checkout"]') : null);
    
    if (field) {
      field.classList.add('invalid-field');
      
      // Для всех полей (включая даты) используем одинаковые .field-error элементы
      // которые вставляются после поля
      const errorMsg = document.createElement('div');
      errorMsg.className = 'field-error';
      errorMsg.textContent = errors[fieldName];
      errorMsg.id = `error-${fieldName}`;
      field.parentNode?.insertBefore(errorMsg, field.nextSibling);
    }
  });
}

/**
 * Показать ошибку для конкретного поля
 * @param {HTMLFormElement} form Элемент формы
 * @param {string} fieldName Имя поля
 * @param {string} message Сообщение об ошибке
 */
function showFormError(form, fieldName, message) {
  // Определяем, какое поле связано с ошибкой
  let field = null;
  
  // Специальные случаи для ошибок с сервера
  if (message.includes('phone') || message.includes('Phone')) {
    field = form.querySelector('#phone') || form.querySelector('[name="phone"]');
    fieldName = 'phone';
  } else if (message.includes('email') || message.includes('Email')) {
    field = form.querySelector('#email') || form.querySelector('[name="email"]');
    fieldName = 'email';
  } else if (message.includes('name') || message.includes('Name')) {
    field = form.querySelector('#name') || form.querySelector('[name="name"]') || form.querySelector('[name="guest_name"]');
    fieldName = 'guest_name';
  } else if (message.includes('check-in') || message.includes('checkin') || message.includes('Check-in')) {
    field = form.querySelector('#checkin') || form.querySelector('[name="checkin"]') || form.querySelector('[name="checkin_date"]');
    fieldName = 'checkin_date';
  } else if (message.includes('check-out') || message.includes('checkout') || message.includes('Check-out')) {
    field = form.querySelector('#checkout') || form.querySelector('[name="checkout"]') || form.querySelector('[name="checkout_date"]');
    fieldName = 'checkout_date';
  } else {
    // Пытаемся найти поле по имени
    field = form.querySelector(`[name="${fieldName}"]`) || form.querySelector(`#${fieldName}`);
  }
  
  // Если поле найдено, показываем ошибку рядом с ним
  if (field && window.showFieldError) {
    window.showFieldError(field, message);
    if (window.flashDateField) {
      window.flashDateField(field);
    }
    field.focus();
  } else {
    // Если поле не найдено, показываем ошибку в контейнере (fallback)
    const errorContainer = form.querySelector('.form-errors') || document.createElement('div');
    errorContainer.className = 'form-errors';
    if (!errorContainer.parentNode) {
      form.insertBefore(errorContainer, form.firstChild);
    }

    let errorMsg = errorContainer.querySelector(`#error-${fieldName}`);
    if (!errorMsg) {
      errorMsg = document.createElement('div');
      errorMsg.className = 'field-error';
      errorMsg.id = `error-${fieldName}`;
      errorContainer.appendChild(errorMsg);
    }
    errorMsg.textContent = message;
  }
}

/**
 * Очистить ошибки формы
 * @param {HTMLFormElement} form Элемент формы
 */
function clearFormErrors(form) {
  // Удаляем классы ошибок с полей
  form.querySelectorAll('.invalid-field').forEach(field => {
    field.classList.remove('invalid-field');
  });

  // Удаляем сообщения об ошибках
  form.querySelectorAll('.field-error').forEach(error => {
    error.remove();
  });

  // Очищаем общий контейнер ошибок
  const errorContainer = form.querySelector('.form-errors');
  if (errorContainer) {
    errorContainer.innerHTML = '';
  }
}

/**
 * Показать состояние загрузки
 */
function showLoadingState() {
  // Создаем overlay загрузки, если его еще нет
  let overlay = document.getElementById('booking-loading-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'booking-loading-overlay';
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
    `;
    overlay.innerHTML = `
      <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
        <div style="margin-bottom: 15px;">Processing your booking...</div>
        <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #4a5568; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
      </div>
    `;
    document.body.appendChild(overlay);

    // Добавляем CSS анимацию для спиннера
    if (!document.getElementById('loading-spinner-style')) {
      const style = document.createElement('style');
      style.id = 'loading-spinner-style';
      style.textContent = `
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }
  }
  overlay.style.display = 'flex';
}

/**
 * Скрыть состояние загрузки
 */
function hideLoadingState() {
  const overlay = document.getElementById('booking-loading-overlay');
  if (overlay) {
    overlay.style.display = 'none';
  }
}

/**
 * Показать сообщение об успешном бронировании
 * @param {HTMLFormElement} form - Форма бронирования
 * @param {Object} options - Опции
 * @param {boolean} options.isAuthenticated - Авторизован ли пользователь
 * @param {Object} options.bookingData - Данные бронирования {name, email, phone}
 */
function showBookingSuccessMessage(form, options = {}) {
  const { isAuthenticated = false, bookingData = {} } = options;
  
  // Создаем контейнер для сообщения
  const messageContainer = document.createElement('div');
  messageContainer.className = 'booking-success-message';
  messageContainer.style.cssText = `
    margin: 20px 0;
    padding: 24px;
    background: var(--card);
    border: 1px solid rgba(61, 220, 151, 0.2);
    border-radius: 16px;
    text-align: left;
  `;
  
  // Текст сообщения
  const messageText = document.createElement('div');
  messageText.style.cssText = 'margin-bottom: 20px;';
  messageText.innerHTML = `
    <h2 style="margin: 0 0 12px; color: var(--text); font-size: 24px; text-align: left;">Your booking has been submitted!</h2>
    <p style="margin: 0 0 8px; color: var(--text); font-size: 16px; line-height: 1.6; text-align: left;">
      We've sent you a confirmation email. Once your booking is approved, you'll be able to proceed with the payment.
    </p>
    <p style="margin: 0; color: var(--text); font-size: 16px; line-height: 1.6; text-align: left;">
      You can also make changes to your booking in your personal account.
    </p>
  `;
  messageContainer.appendChild(messageText);
  
  // Если пользователь не залогинен - показываем меню Sign In / Create Account
  if (!isAuthenticated) {
    const authMenuContainer = document.createElement('div');
    authMenuContainer.id = 'booking-success-auth-menu';
    authMenuContainer.style.cssText = 'margin-top: 24px;';
    messageContainer.appendChild(authMenuContainer);
    
    // Вставляем сообщение перед формой
    form.parentNode.insertBefore(messageContainer, form);
    
    // Скрываем форму
    form.style.display = 'none';
    
    // Инициализируем меню авторизации с предзаполненными данными
    if (window.createAuthMenu) {
      setTimeout(() => {
        window.createAuthMenu('#booking-success-auth-menu', {
          defaultTab: 'register',
          prefillData: {
            name: bookingData.name || '',
            email: bookingData.email || '',
            phone: bookingData.phone || ''
          },
          onLogin: (user) => {
            // После входа перенаправляем на dashboard
            window.location.href = 'dashboard.html';
          },
          onRegister: (user) => {
            // После регистрации перенаправляем на dashboard
            window.location.href = 'dashboard.html';
          }
        });
      }, 100);
    }
  } else {
    // Если пользователь залогинен - показываем кнопку "My Account"
    const accountButton = document.createElement('a');
    accountButton.href = 'dashboard.html';
    accountButton.className = 'btn primary';
    accountButton.textContent = 'My Account';
    accountButton.style.cssText = 'margin-top: 20px; display: inline-block; text-align: left;';
    messageContainer.appendChild(accountButton);
    
    // Вставляем сообщение перед формой
    form.parentNode.insertBefore(messageContainer, form);
    
    // Скрываем форму
    form.style.display = 'none';
  }
  
  // Прокручиваем к сообщению
  messageContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Валидация данных формы бронирования массажа
 * @param {Object} formData Данные формы
 * @returns {Object} {valid: boolean, errors: Object}
 */
function validateMassageForm(formData) {
  const errors = {};

  // Валидация в правильной последовательности:
  // 1. Сначала type (тип массажа)
  if (!formData.type || !formData.type.trim()) {
    errors.type = 'Massage type is required';
  }

  // 2. Потом duration (длительность)
  if (!formData.duration || !formData.duration.trim()) {
    errors.duration = 'Duration is required';
  }

  // 3. Потом date (дата)
  if (!formData.date || !formData.date.trim()) {
    errors.date = 'Date is required';
  } else {
    // Проверяем, что дата не в прошлом
    const date = parseLocalDate(formData.date);
    if (date) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
      const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      
      if (dateOnly < todayDateOnly) {
        errors.date = 'Date cannot be in the past';
      }
    } else {
      errors.date = 'Invalid date format';
    }
  }

  // 4. Потом time (время)
  if (!formData.time || !formData.time.trim()) {
    errors.time = 'Time is required';
  } else {
    // Проверяем, что время в допустимом диапазоне (9:00 - 21:00)
    const timeValue = formData.time;
    const [hours, minutes] = timeValue.split(':').map(Number);
    const timeInMinutes = hours * 60 + minutes;
    const minTime = 9 * 60; // 9:00 AM
    const maxTime = 21 * 60; // 9:00 PM
    
    if (timeInMinutes < minTime || timeInMinutes > maxTime) {
      errors.time = 'Massage appointments are only available between 9:00 AM and 9:00 PM';
    }
  }

  // 5. Потом name (имя)
  if (!formData.name || !formData.name.trim()) {
    errors.name = 'Name is required';
  }

  // 6. Потом email (почта)
  if (!formData.email || !formData.email.trim()) {
    errors.email = 'Email is required';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Invalid email address';
  }

  // 7. Потом withRoom (остановка с нами)
  if (!formData.withRoom || !formData.withRoom.trim()) {
    errors.withRoom = 'Please specify if you are staying with us';
  }

  // 8. Потом phone (телефон)
  if (!formData.phone || !formData.phone.trim()) {
    errors.phone = 'Phone number is required';
  } else {
    // Проверяем формат телефона (минимум 10 цифр)
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(formData.phone.trim())) {
      errors.phone = 'Invalid phone number';
    }
  }

  return {
    valid: Object.keys(errors).length === 0,
    errors
  };
}

/**
 * Обработка формы бронирования массажа
 * @param {HTMLFormElement} form Элемент формы
 * @returns {Promise<boolean>} Успешность обработки
 */
async function handleMassageForm(form) {
  try {
    console.log('MassageAPI.handleMassageForm: Starting...');
    
    // Отключаем стандартную HTML5 валидацию браузера
    if (form.checkValidity) {
      form.setAttribute('novalidate', '');
    }
    
    // Собираем данные формы
    const formData = {
      type: form.querySelector('#type')?.value || '',
      duration: form.querySelector('#duration')?.value || '',
      date: form.querySelector('#date')?.value || '',
      time: form.querySelector('#time')?.value || '',
      name: form.querySelector('#name')?.value || '',
      email: form.querySelector('#email')?.value || '',
      phone: form.querySelector('#phone')?.value || '',
      withRoom: form.querySelector('#with-room')?.value || ''
    };
    
    console.log('MassageAPI.handleMassageForm: Collected form data:', formData);

    // Валидация
    console.log('MassageAPI: Validating form data...', formData);
    const validation = validateMassageForm(formData);
    console.log('MassageAPI: Validation result:', validation);
    if (!validation.valid) {
      console.error('MassageAPI: Validation failed:', validation.errors);
      
      // Находим первое поле с ошибкой в правильной последовательности:
      // 1. type, 2. duration, 3. date, 4. time, 5. name, 6. email, 7. withRoom, 8. phone
      const typeEl = form.querySelector('#type');
      const durationEl = form.querySelector('#duration');
      const dateEl = form.querySelector('#date');
      const timeEl = form.querySelector('#time');
      const nameEl = form.querySelector('#name');
      const emailEl = form.querySelector('#email');
      const withRoomEl = form.querySelector('#with-room');
      const phoneEl = form.querySelector('#phone');
      
      // 1. Сначала type
      if (validation.errors.type && typeEl) {
        window.showFieldError(typeEl, validation.errors.type);
        typeEl.classList.add('flash-invalid');
        typeEl.focus();
        return false;
      }
      
      // 2. Потом duration
      if (validation.errors.duration && durationEl) {
        window.showFieldError(durationEl, validation.errors.duration);
        durationEl.classList.add('flash-invalid');
        durationEl.focus();
        return false;
      }
      
      // 3. Потом date
      if (validation.errors.date && dateEl) {
        window.showFieldError(dateEl, validation.errors.date);
        dateEl.classList.add('flash-invalid');
        dateEl.focus();
        return false;
      }
      
      // 4. Потом time
      if (validation.errors.time && timeEl) {
        window.showFieldError(timeEl, validation.errors.time);
        timeEl.classList.add('flash-invalid');
        timeEl.focus();
        return false;
      }
      
      // 5. Потом name
      if (validation.errors.name && nameEl) {
        window.showFieldError(nameEl, validation.errors.name);
        nameEl.classList.add('flash-invalid');
        nameEl.focus();
        return false;
      }
      
      // 6. Потом email
      if (validation.errors.email && emailEl) {
        window.showFieldError(emailEl, validation.errors.email);
        emailEl.classList.add('flash-invalid');
        emailEl.focus();
        return false;
      }
      
      // 7. Потом withRoom
      if (validation.errors.withRoom && withRoomEl) {
        window.showFieldError(withRoomEl, validation.errors.withRoom);
        withRoomEl.classList.add('flash-invalid');
        withRoomEl.focus();
        return false;
      }
      
      // 8. Потом phone
      if (validation.errors.phone && phoneEl) {
        window.showFieldError(phoneEl, validation.errors.phone);
        phoneEl.classList.add('flash-invalid');
        phoneEl.focus();
        return false;
      }
      
      return false;
    }

    // Очищаем все ошибки перед отправкой
    clearFormErrors(form);

    // Здесь можно добавить отправку данных на сервер через API
    // Пока используем старое поведение - сохраняем в localStorage
    document.dispatchEvent(new CustomEvent('btb:order:record', { detail: {
      kind: 'massage',
      type: formData.type || '',
      duration: formData.duration || '',
      date: formData.date || '',
      time: formData.time || '',
      name: formData.name || '',
      phone: formData.phone || '',
      email: formData.email || '',
      withRoom: formData.withRoom || '',
      ts: Date.now(),
    }}));

    // Показываем сообщение об успехе
    alert(`Massage booking (${formData.type}, ${formData.duration} min, staying: ${formData.withRoom}) sent!\n${formData.date} at ${formData.time}. We will confirm by email.`);
    
    // Показываем напоминание о комнате, если нужно
    const reminder = document.getElementById('room-reminder');
    if (formData.withRoom === 'yes' && reminder) {
      // Проверяем, есть ли уже бронирование комнаты
      const orders = JSON.parse(localStorage.getItem('btb_orders') || '[]');
      const hasRoomOrder = orders.some(order => order.kind === 'room');
      if (!hasRoomOrder) {
        localStorage.setItem('btb_room_reminder_shown', '1');
        reminder.style.display = 'block';
        reminder.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
    
    form.reset();
    return true;
  } catch (error) {
    console.error('MassageAPI.handleMassageForm: Exception caught:', error);
    showFormError(form, 'submit', error.message || 'An error occurred');
    return false;
  }
}

// Экспорт функций для использования в других модулях
window.BookingAPI = {
  checkAvailability,
  createBooking,
  getBooking,
  validateBookingForm,
  handleBookingForm,
  validateMassageForm,
  handleMassageForm,
  showFormErrors,
  showFormError,
  clearFormErrors
};

