// Import common utilities
// Note: utils.js should be loaded before this file in HTML

// Reveal on scroll - now using AnimationUtils from utils.js
const revealElements = () => {
  // Use the common utility if available, otherwise fallback to local implementation
  if (window.AnimationUtils && window.AnimationUtils.initReveal) {
    window.AnimationUtils.initReveal();
  } else {
    const elements = document.querySelectorAll('.reveal');
    const reveal = () => {
      const trigger = window.innerHeight * 0.88;
      elements.forEach(el => {
        const rect = el.getBoundingClientRect();
        if (rect.top < trigger) {
          el.classList.add('visible');
        }
      });
    };
    reveal();
    window.addEventListener('scroll', reveal, { passive: true });
  }
};

// Global flash helper for invalid fields - now using FormUtils from utils.js
function flashInvalid(el) {
  // Use the common utility if available, otherwise fallback to local implementation
  if (window.FormUtils && window.FormUtils.flashInvalid) {
    window.FormUtils.flashInvalid(el);
  } else {
    try {
      if (!el) return;
      el.classList.remove('flash-invalid');
      void el.offsetWidth; // restart animation
      el.classList.add('flash-invalid');
      // auto-clear so red border does not persist
      setTimeout(() => { try { el.classList.remove('flash-invalid'); } catch(_) {} }, 700);
    } catch (_) {}
  }
}

// Ensure flashing is visible for enhanced date inputs (flash both real and display proxy)
// КРИТИЧНО: Для полей дат используется Flatpickr с altInput
// Нужно мигать видимым altInput, а не скрытым input
function flashDateField(realInput) {
  if (!realInput) return;
  
  // Проверяем, используется ли Flatpickr с altInput
  if (typeof flatpickr !== 'undefined' && realInput._flatpickr) {
    const fpInstance = realInput._flatpickr;
    // Если есть altInput (видимое поле), мигаем его
    if (fpInstance.altInput) {
      flashInvalid(fpInstance.altInput);
    }
    // Также мигаем скрытым input для браузерной валидации
    flashInvalid(realInput);
  } else {
    // Fallback: используем старую логику для полей без Flatpickr
    flashInvalid(realInput);
    try {
      const proxy = realInput.previousElementSibling;
      if (proxy && proxy.tagName === 'INPUT') {
        flashInvalid(proxy);
      }
    } catch (_) {}
  }
}

// Показать ошибку для поля в стиле .field-error (универсальная функция для всех полей)
function showFieldError(input, message) {
  if (!input || !message) return;
  
  // Находим видимое поле (altInput для Flatpickr или само поле)
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
  
  // Добавляем класс invalid-field к видимому полю
  visibleField.classList.add('invalid-field');
  if (input !== visibleField) {
    input.classList.add('invalid-field');
  }
  
  // Создаем элемент ошибки
  const errorMsg = document.createElement('div');
  errorMsg.className = 'field-error';
  errorMsg.textContent = message;
  errorMsg.id = errorId;
  
  // Вставляем ошибку после видимого поля
  if (visibleField.parentNode) {
    visibleField.parentNode.insertBefore(errorMsg, visibleField.nextSibling);
  }
}

// Показать ошибку для поля даты в стиле .field-error (как для обычных полей)
function showDateFieldError(input, message) {
  showFieldError(input, message);
}

// Очистить ошибку для поля (универсальная функция для всех полей)
function clearFieldError(input) {
  if (!input) return;
  
  // Находим видимое поле (altInput для Flatpickr или само поле)
  let visibleField = input;
  if (typeof flatpickr !== 'undefined' && input._flatpickr) {
    const fpInstance = input._flatpickr;
    if (fpInstance.altInput) {
      visibleField = fpInstance.altInput;
    }
  }
  
  // Удаляем ошибку, если есть
  const errorId = `error-${input.id || input.name || 'field'}`;
  const existingError = visibleField.parentNode?.querySelector(`#${errorId}`);
  if (existingError) {
    existingError.remove();
  }
  
  // Удаляем классы invalid-field и flash-invalid
  visibleField.classList.remove('invalid-field', 'flash-invalid');
  if (input !== visibleField) {
    input.classList.remove('invalid-field', 'flash-invalid');
  }
}

// Очистить ошибку для поля даты (алиас для обратной совместимости)
function clearDateFieldError(input) {
  clearFieldError(input);
}

function clearDateFieldFlash(realInput) {
  try {
    if (!realInput) return;
    realInput.classList.remove('flash-invalid');
    const proxy = realInput.previousElementSibling;
    if (proxy && proxy.tagName === 'INPUT') proxy.classList.remove('flash-invalid');
  } catch (_) {}
}

// Lightweight custom validation bubble for hidden date inputs
// Lightweight custom validation bubble for hidden date inputs - now using ValidationUtils from utils.js
function showValidationBubble(target, message) {
  // Use the common utility if available, otherwise fallback to local implementation
  if (window.ValidationUtils && window.ValidationUtils.showBubble) {
    window.ValidationUtils.showBubble(target, message);
  } else {
    try {
      hideValidationBubble();
      const bubble = document.createElement('div');
      bubble.id = 'btb-bubble';
      bubble.className = 'btb-bubble';
      const ic = document.createElement('div'); ic.className = 'btb-ic'; ic.textContent = '!';
      const msg = document.createElement('div'); msg.className = 'btb-msg'; msg.innerHTML = String(message || '').replace(/\n/g, '<br>');
      bubble.appendChild(ic); bubble.appendChild(msg);
      document.body.appendChild(bubble);
      const rect = target.getBoundingClientRect();
      const top = Math.max(8, rect.top - 8);
      const left = Math.min(window.innerWidth - bubble.offsetWidth - 8, rect.right + 12);
      bubble.style.top = `${top}px`;
      bubble.style.left = `${left}px`;
      // Do not auto-hide; keep visible until dates are corrected
    } catch (_) {}
  }
}
function hideValidationBubble() {
  const ex = document.getElementById('btb-bubble');
  if (ex && ex.parentNode) ex.parentNode.removeChild(ex);
}

// expose for order.html
window.showValidationBubble = showValidationBubble;
window.hideValidationBubble = hideValidationBubble;

// expose for booking.js
window.showDateErrorNotification = showDateErrorNotification;
window.flashDateField = flashDateField;
window.showDateFieldError = showDateFieldError;
window.showFieldError = showFieldError;
window.clearDateFieldError = clearDateFieldError;
window.clearFieldError = clearFieldError;

// Year in footer
const setYear = () => {
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();
};

// Booking form validation and confirmation
const initBookingForms = () => {
  const forms = document.querySelectorAll('form.booking-form:not([data-custom-handler])');
  forms.forEach(form => {
    // enforce checkout >= checkin
    attachCheckinCheckoutConstraint(form, '#checkin', '#checkout');
    // Настраиваем очистку ошибок для всех форм
    setupFieldErrorClearing(form);
    prefillContact(form);
    // Отмечаем, что форма имеет обработчик submit
    form.dataset.hasSubmitHandler = 'true';
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopImmediatePropagation();
      const checkinEl = form.querySelector('input[name="checkin"]');
      const checkoutEl = form.querySelector('input[name="checkout"]');
      const checkin = checkinEl ? checkinEl.value : '';
      const checkout = checkoutEl ? checkoutEl.value : '';
      if (!checkin || !checkout) {
        // Для полей дат используем кастомные подсказки (.field-error)
        // Не используем reportValidity(), так как он показывает HTML5 валидацию браузера
        if (!checkin && checkinEl) {
          if (window.showDateFieldError) {
            window.showDateFieldError(checkinEl, 'Please select a check-in date.');
          }
        } else if (!checkout && checkoutEl) {
          if (window.showDateFieldError) {
            window.showDateFieldError(checkoutEl, 'Please select a check-out date.');
          }
        }
        return;
      }
      const inDate = parseLocalDate(checkin);
      const outDate = parseLocalDate(checkout);
      if (outDate <= inDate) {
        if (checkoutEl) {
          // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
          checkoutEl.setCustomValidity('');
          // Для полей дат используем .field-error элементы (унифицированная система)
          if (window.showDateFieldError) {
            window.showDateFieldError(checkoutEl, DATE_RANGE_MSG);
          }
        }
        return;
      }
      if (checkoutEl) checkoutEl.setCustomValidity('');
      const roomName = form.getAttribute('data-room') || 'Room';
      alert(`${roomName}: booking request sent!\nWe will contact you to confirm.`);
      form.reset();
    });
  });
};

// Massage form
const initMassageForm = () => {
  const form = document.querySelector('form#massage-form');
  if (!form) return;
  prefillContact(form);
  const typeSel = form.querySelector('select#type');
  const durationSel = form.querySelector('select#duration');
  const withRoomSel = form.querySelector('select#with-room');
  const reminder = document.getElementById('room-reminder');
  // Temporarily disable auto-show from localStorage; keep hidden until successful booking submit
  if (reminder) {
    reminder.style.display = 'none';
  }
  const setDurations = () => {
    if (!durationSel) return;
    const type = typeSel ? typeSel.value : '';
    let options = [];
    if (type === 'Reiki Energy Healing') {
      options = [
        { v: '15', t: '15 minutes' },
        { v: '30', t: '30 minutes' },
      ];
    } else {
      options = [
        { v: '60', t: '60 minutes' },
        { v: '90', t: '90 minutes' },
      ];
    }
    durationSel.innerHTML = '';
    options.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.v; opt.textContent = o.t;
      durationSel.appendChild(opt);
    });
  };
  if (typeSel) typeSel.addEventListener('change', setDurations);
  setDurations();
  
  // Настраиваем очистку ошибок
  setupMassageFieldErrorClearing(form);
  
  // Отмечаем, что форма имеет обработчик submit
  form.dataset.hasSubmitHandler = 'true';
  
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    
    // Используем новую функцию обработки формы массажа
    if (window.BookingAPI && window.BookingAPI.handleMassageForm) {
      const success = await window.BookingAPI.handleMassageForm(form);
      if (success) {
        // Успешное создание бронирования - форма уже обработана в handleMassageForm
      } else {
        // Ошибка создания бронирования - ошибки уже показаны в handleMassageForm
      }
    } else {
      // Fallback на старое поведение, если booking.js не загружен
      const dateEl = form.querySelector('input[name="date"]');
      const timeEl = form.querySelector('input[name="time"]');
      const nameEl = form.querySelector('input[name="name"]');
      const emailEl = form.querySelector('input[name="email"]');
      const phoneEl = form.querySelector('input[name="phone"]');
      const type = typeSel ? typeSel.value : '';
      const dur = durationSel ? durationSel.value : '';
      const withRoom = withRoomSel ? withRoomSel.value : '';

      // Ordered validation с новым стилем подсказок
      if (!type) { 
        if (window.showFieldError) window.showFieldError(typeSel, 'Massage type is required');
        typeSel.classList.add('flash-invalid');
        typeSel.focus();
        return; 
      }
      if (!dur) { 
        if (window.showFieldError) window.showFieldError(durationSel, 'Duration is required');
        durationSel.classList.add('flash-invalid');
        durationSel.focus();
        return; 
      }
      if (!dateEl.value) { 
        if (window.showFieldError) window.showFieldError(dateEl, 'Date is required');
        dateEl.classList.add('flash-invalid');
        dateEl.focus();
        return; 
      }
      if (!timeEl.value) { 
        if (window.showFieldError) window.showFieldError(timeEl, 'Time is required');
        timeEl.classList.add('flash-invalid');
        timeEl.focus();
        return; 
      }
      
      // Validate time is within allowed range (9:00 - 21:00)
      const timeValue = timeEl.value;
      const [hours, minutes] = timeValue.split(':').map(Number);
      const timeInMinutes = hours * 60 + minutes;
      const minTime = 9 * 60; // 9:00 AM
      const maxTime = 21 * 60; // 9:00 PM
      
      if (timeInMinutes < minTime || timeInMinutes > maxTime) {
        if (window.showFieldError) window.showFieldError(timeEl, 'Massage appointments are only available between 9:00 AM and 9:00 PM');
        timeEl.classList.add('flash-invalid');
        timeEl.focus();
        return;
      }
      
      if (!nameEl.value.trim()) { 
        if (window.showFieldError) window.showFieldError(nameEl, 'Name is required');
        nameEl.classList.add('flash-invalid');
        nameEl.focus();
        return; 
      }
      if (!emailEl.value.trim()) { 
        if (window.showFieldError) window.showFieldError(emailEl, 'Email is required');
        emailEl.classList.add('flash-invalid');
        emailEl.focus();
        return; 
      }
      if (!withRoom) { 
        if (window.showFieldError) window.showFieldError(withRoomSel, 'Please specify if you are staying with us');
        withRoomSel.classList.add('flash-invalid');
        withRoomSel.focus();
        return; 
      }
      if (!phoneEl.value.trim()) { 
        if (window.showFieldError) window.showFieldError(phoneEl, 'Phone number is required');
        phoneEl.classList.add('flash-invalid');
        phoneEl.focus();
        return; 
      }

      // Record massage order (independent of staying choice)
      document.dispatchEvent(new CustomEvent('btb:order:record', { detail: {
        kind: 'massage',
        type: type || '',
        duration: dur || '',
        date: dateEl.value || '',
        time: timeEl.value || '',
        name: nameEl.value || '',
        phone: phoneEl.value || '',
        email: emailEl.value || '',
        withRoom: withRoom || '',
        ts: Date.now(),
      }}));

      // Show reminder only after successful submit if staying == yes
      if (withRoom === 'yes' && reminder && !hasOrder('room')) {
        localStorage.setItem('btb_room_reminder_shown', '1');
        reminder.style.display = 'block';
        reminder.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      alert(`Massage booking (${type}, ${dur} min, staying: ${withRoom}) sent!\n${dateEl.value} at ${timeEl.value}. We will confirm by email.`);
      form.reset();
      setDurations();
    }
  });
};

// Настройка очистки ошибок для формы массажа
function setupMassageFieldErrorClearing(form) {
  if (!form) return;
  
  // Поле type - очищаем ошибку при выборе значения
  const typeSelect = form.querySelector('#type');
  if (typeSelect) {
    typeSelect.addEventListener('change', () => {
      if (typeSelect.value && window.clearFieldError) {
        window.clearFieldError(typeSelect);
      }
    });
  }
  
  // Поле duration - очищаем ошибку при выборе значения
  const durationSelect = form.querySelector('#duration');
  if (durationSelect) {
    durationSelect.addEventListener('change', () => {
      if (durationSelect.value && window.clearFieldError) {
        window.clearFieldError(durationSelect);
      }
    });
  }
  
  // Поле date - очищаем ошибку при выборе корректной даты
  const dateInput = form.querySelector('#date');
  if (dateInput) {
    const clearDateError = () => {
      if (dateInput.value && window.clearFieldError) {
        const date = parseLocalDate(dateInput.value);
        if (date) {
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
          const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          
          if (dateOnly >= todayDateOnly) {
            window.clearFieldError(dateInput);
          }
        }
      }
    };
    dateInput.addEventListener('input', clearDateError);
    dateInput.addEventListener('change', clearDateError);
  }
  
  // Поле time - очищаем ошибку при выборе корректного времени
  const timeInput = form.querySelector('#time');
  if (timeInput) {
    const clearTimeError = () => {
      if (timeInput.value && window.clearFieldError) {
        const timeValue = timeInput.value;
        const [hours, minutes] = timeValue.split(':').map(Number);
        const timeInMinutes = hours * 60 + minutes;
        const minTime = 9 * 60; // 9:00 AM
        const maxTime = 21 * 60; // 9:00 PM
        
        if (timeInMinutes >= minTime && timeInMinutes <= maxTime) {
          window.clearFieldError(timeInput);
        }
      }
    };
    timeInput.addEventListener('input', clearTimeError);
    timeInput.addEventListener('change', clearTimeError);
  }
  
  // Поле name - очищаем ошибку при вводе текста
  const nameInput = form.querySelector('#name');
  if (nameInput) {
    nameInput.addEventListener('input', () => {
      if (nameInput.value.trim() && window.clearFieldError) {
        window.clearFieldError(nameInput);
      }
    });
  }
  
  // Поле email - очищаем ошибку при вводе корректного email
  const emailInput = form.querySelector('#email');
  if (emailInput) {
    emailInput.addEventListener('input', () => {
      const emailValue = emailInput.value.trim();
      if (emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue) && window.clearFieldError) {
        window.clearFieldError(emailInput);
      }
    });
  }
  
  // Поле withRoom - очищаем ошибку при выборе значения
  const withRoomSelect = form.querySelector('#with-room');
  if (withRoomSelect) {
    withRoomSelect.addEventListener('change', () => {
      if (withRoomSelect.value && window.clearFieldError) {
        window.clearFieldError(withRoomSelect);
      }
    });
  }
  
  // Поле phone - очищаем ошибку при вводе корректного телефона
  const phoneInput = form.querySelector('#phone');
  if (phoneInput) {
    phoneInput.addEventListener('input', () => {
      const phoneValue = phoneInput.value.trim();
      const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
      if (phoneValue && phoneRegex.test(phoneValue) && window.clearFieldError) {
        window.clearFieldError(phoneInput);
      }
    });
  }
}

// Универсальная функция для настройки автоматической очистки ошибок при корректном заполнении
// Используется для всех форм бронирования комнат
function setupFieldErrorClearing(form) {
  if (!form) return;
  
    // Поля дат - очищаем ошибки при выборе корректной даты
    const checkinInput = form.querySelector('#checkin') || form.querySelector('[name="checkin"]');
    const checkoutInput = form.querySelector('#checkout') || form.querySelector('[name="checkout"]');
    
    if (checkinInput) {
      const clearCheckinError = () => {
        if (!checkinInput.value) return;
        
        // Проверяем, что дата корректна (не в прошлом и не заблокирована)
        const checkinDate = parseLocalDate(checkinInput.value);
        if (checkinDate) {
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          const checkinDateOnly = new Date(checkinDate.getFullYear(), checkinDate.getMonth(), checkinDate.getDate());
          const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          
          // Очищаем ошибку только если дата не в прошлом
          if (checkinDateOnly >= todayDateOnly && window.clearFieldError) {
            window.clearFieldError(checkinInput);
            
            // Если check-out уже выбран и стал корректным после изменения check-in, очищаем его ошибку
            if (checkoutInput && checkoutInput.value) {
              const checkoutDate = parseLocalDate(checkoutInput.value);
              if (checkoutDate) {
                const checkinPlusOne = new Date(checkinDate);
                checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
                if (checkoutDate > checkinPlusOne) {
                  window.clearFieldError(checkoutInput);
                }
              }
            }
          }
        }
      };
      checkinInput.addEventListener('input', clearCheckinError);
      checkinInput.addEventListener('change', clearCheckinError);
    }
    
    if (checkoutInput) {
      const clearCheckoutError = () => {
        if (!checkoutInput.value) return;
        
        // Проверяем, что дата корректна (позже checkin + 1 день)
        const checkoutDate = parseLocalDate(checkoutInput.value);
        if (checkoutDate && checkinInput && checkinInput.value) {
          const checkinDate = parseLocalDate(checkinInput.value);
          if (checkinDate) {
            const checkinPlusOne = new Date(checkinDate);
            checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
            
            // Очищаем ошибку только если checkout > checkin + 1
            if (checkoutDate > checkinPlusOne && window.clearFieldError) {
              window.clearFieldError(checkoutInput);
            }
          }
        } else if (checkoutDate && window.clearFieldError) {
          // Если checkin не выбран, очищаем ошибку checkout
          window.clearFieldError(checkoutInput);
        }
      };
      checkoutInput.addEventListener('input', clearCheckoutError);
      checkoutInput.addEventListener('change', clearCheckoutError);
    }
    
    // Поле name - очищаем ошибку при вводе текста
    const nameInput = form.querySelector('#name') || form.querySelector('[name="name"]') || form.querySelector('[name="guest_name"]');
    if (nameInput) {
      nameInput.addEventListener('input', () => {
        if (nameInput.value.trim() && window.clearFieldError) {
          window.clearFieldError(nameInput);
        }
      });
    }
    
    // Поле phone - очищаем ошибку при вводе корректного телефона
    const phoneInput = form.querySelector('#phone') || form.querySelector('[name="phone"]');
    if (phoneInput) {
      phoneInput.addEventListener('input', () => {
        const phoneValue = phoneInput.value.trim();
        // Проверяем формат телефона (минимум 10 цифр)
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (phoneValue && phoneRegex.test(phoneValue) && window.clearFieldError) {
          window.clearFieldError(phoneInput);
        }
      });
    }
    
    // Поле email - очищаем ошибку при вводе корректного email
    const emailInput = form.querySelector('#email') || form.querySelector('[name="email"]');
    if (emailInput) {
      emailInput.addEventListener('input', () => {
        const emailValue = emailInput.value.trim();
        if (emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue) && window.clearFieldError) {
          window.clearFieldError(emailInput);
        }
      });
    }
    
    // Поле guests - очищаем ошибку при выборе значения
    const guestsSelect = form.querySelector('#guests') || form.querySelector('[name="guests"]') || form.querySelector('[name="guests_count"]');
    if (guestsSelect) {
      guestsSelect.addEventListener('change', () => {
        if (guestsSelect.value && parseInt(guestsSelect.value) >= 1 && window.clearFieldError) {
          window.clearFieldError(guestsSelect);
        }
      });
    }
    
    // Поле pets - очищаем ошибку при выборе значения
    const petsSelect = form.querySelector('#pets') || form.querySelector('[name="pets"]');
    if (petsSelect) {
      petsSelect.addEventListener('change', () => {
        if (petsSelect.value && window.clearFieldError) {
          window.clearFieldError(petsSelect);
        }
      });
    }
}

// Скрыть иконку домика на страницах комнат
function hideOrderIndicatorOnRoomPages() {
  // Проверяем, находимся ли мы на странице комнаты
  const isRoomPage = document.querySelector('form.booking-form[data-room]') !== null ||
                     window.location.pathname.includes('room-') ||
                     window.location.pathname.includes('room_') ||
                     document.querySelector('.room-hero') !== null;
  
  if (isRoomPage) {
    // Скрываем иконку домика, если она существует (проверяем несколько раз для надежности)
    const hideIndicator = () => {
      const orderIndicator = document.querySelector('.order-indicator');
      if (orderIndicator) {
        orderIndicator.style.display = 'none';
        orderIndicator.style.visibility = 'hidden';
        orderIndicator.style.opacity = '0';
        orderIndicator.remove(); // Удаляем элемент полностью
      }
    };
    
    // Скрываем сразу
    hideIndicator();
    
    // Скрываем после небольшой задержки (на случай, если иконка создается асинхронно)
    setTimeout(hideIndicator, 100);
    setTimeout(hideIndicator, 500);
    setTimeout(hideIndicator, 1000);
    
    // Отключаем обработчик событий btb:order:record для страниц комнат
    // чтобы иконка не появлялась при бронировании
    document.addEventListener('btb:order:record', (e) => {
      e.stopImmediatePropagation();
      hideIndicator();
    }, true); // Используем capture phase для раннего перехвата
    
    // Также перехватываем события после загрузки DOM
    document.addEventListener('DOMContentLoaded', hideIndicator);
    
    // Наблюдаем за изменениями DOM и скрываем иконку, если она появляется
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1) { // Element node
            if (node.classList && node.classList.contains('order-indicator')) {
              hideIndicator();
            }
            // Также проверяем дочерние элементы
            const indicator = node.querySelector && node.querySelector('.order-indicator');
            if (indicator) {
              hideIndicator();
            }
          }
        });
      });
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
}

// Room booking form validation for Basement — Queen bed and other rooms
// Единая функция для инициализации формы бронирования
// Используется для всех страниц комнат
function initBookingForm(form, roomName) {
  if (!form || !roomName) return;
  
  // Скрываем иконку домика на страницах комнат
  hideOrderIndicatorOnRoomPages();
  
  const flash = (el) => flashDateField(el);
  
  // Устанавливаем constraint для check-in/check-out
  attachCheckinCheckoutConstraint(form, '#checkin', '#checkout');
  
  // Настраиваем очистку ошибок после инициализации формы
  setupFieldErrorClearing(form);
  
  // Инициализируем блокировку занятых дат
  initBlockedDatesForRoom(form, roomName);
  
  // Обработка wellness section (только для Basement)
  const wellnessSection = document.getElementById('wellness-section');
  const showWellnessReminder = () => {
    if (wellnessSection) {
      wellnessSection.style.display = 'block';
      wellnessSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  
  // Проверяем, должна ли wellness section быть показана на основе localStorage
  // По умолчанию показываем секцию, если она не была скрыта пользователем
  if (wellnessSection) {
    const wellnessHidden = localStorage.getItem('btb_wellness_hidden');
    if (wellnessHidden !== '1') {
      wellnessSection.style.display = 'block';
    } else {
      wellnessSection.style.display = 'none';
    }
  }
  
  // Отмечаем, что форма имеет обработчик submit
  form.dataset.hasSubmitHandler = 'true';
  
  // Обработчик submit формы
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    
    // Получаем элементы формы
    const checkin = form.querySelector('#checkin');
    const checkout = form.querySelector('#checkout');
    const name = form.querySelector('#name');
    const email = form.querySelector('#email');
    const guests = form.querySelector('#guests');
    const pets = form.querySelector('#pets');
    const phone = form.querySelector('#phone');
    
    // Закрываем календари Flatpickr, если они открыты
    if (typeof flatpickr !== 'undefined') {
      try {
        const checkinFp = checkin?._flatpickr;
        const checkoutFp = checkout?._flatpickr;
        if (checkinFp && checkinFp.isOpen) {
          checkinFp.close();
        }
        if (checkoutFp && checkoutFp.isOpen) {
          checkoutFp.close();
        }
      } catch (e) {
        // Игнорируем ошибки при закрытии календарей
      }
    }

    // Синхронизируем значения из Flatpickr, если они используются
    if (typeof flatpickr !== 'undefined') {
      try {
        // Получаем экземпляры Flatpickr правильным способом
        const checkinFp = checkin._flatpickr || (checkin.dataset.flatpickrInitialized ? flatpickr(checkin) : null);
        const checkoutFp = checkout._flatpickr || (checkout.dataset.flatpickrInitialized ? flatpickr(checkout) : null);
        
        // Синхронизируем check-in - пробуем разные способы получения значения
        let checkinValue = checkin.value;
        if (checkinFp) {
          if (checkinFp.selectedDates && checkinFp.selectedDates.length > 0) {
            checkinValue = checkinFp.formatDate(checkinFp.selectedDates[0], 'Y-m-d');
          } else if (checkinFp.input && checkinFp.input.value) {
            checkinValue = checkinFp.input.value;
          } else if (checkinFp.altInput && checkinFp.altInput.value) {
            // Если используется altInput, нужно получить значение из реального input
            checkinValue = checkin.value || checkinFp.input.value;
          }
          if (checkinValue && checkinValue !== checkin.value) {
            checkin.value = checkinValue;
            checkin.type = 'date';
            checkin.dispatchEvent(new Event('input', { bubbles: true }));
            checkin.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
        
        // Синхронизируем check-out
        let checkoutValue = checkout.value;
        if (checkoutFp) {
          if (checkoutFp.selectedDates && checkoutFp.selectedDates.length > 0) {
            checkoutValue = checkoutFp.formatDate(checkoutFp.selectedDates[0], 'Y-m-d');
          } else if (checkoutFp.input && checkoutFp.input.value) {
            checkoutValue = checkoutFp.input.value;
          } else if (checkoutFp.altInput && checkoutFp.altInput.value) {
            checkoutValue = checkout.value || checkoutFp.input.value;
          }
          if (checkoutValue && checkoutValue !== checkout.value) {
            checkout.value = checkoutValue;
            checkout.type = 'date';
            checkout.dispatchEvent(new Event('input', { bubbles: true }));
            checkout.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      } catch (error) {
        console.error('Error syncing Flatpickr values:', error);
        // Продолжаем с текущими значениями inputs
      }
    }

    // Валидация полей в правильной последовательности:
    // 1. Сначала check-in (въезд)
    if (!checkin.value) {
      if (window.flashDateField) {
        window.flashDateField(checkin);
      } else {
        flash(checkin);
      }
      // Для полей дат используем .field-error элементы (как для обычных полей)
      if (window.showDateFieldError) {
        window.showDateFieldError(checkin, 'Please select a check-in date.');
      }
      checkin.focus();
      return;
    }
    // 2. Потом check-out (выезд)
    if (!checkout.value) {
      if (window.flashDateField) {
        window.flashDateField(checkout);
      } else {
        flash(checkout);
      }
      // Для полей дат используем .field-error элементы (как для обычных полей)
      if (window.showDateFieldError) {
        window.showDateFieldError(checkout, 'Please select a check-out date.');
      }
      checkout.focus();
      return;
    }
    // 3. Потом name (имя)
    if (!name.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(name);
      } else {
        flash(name);
      }
      // Используем .field-error элементы (как для полей дат)
      if (window.showFieldError) {
        window.showFieldError(name, 'Name is required');
      }
      name.focus();
      return;
    }
    // 4. Потом phone (телефон)
    if (!phone.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(phone);
      } else {
        flash(phone);
      }
      // Используем .field-error элементы (как для полей дат)
      if (window.showFieldError) {
        window.showFieldError(phone, 'Phone number is required');
      }
      phone.focus();
      return;
    }
    // 5. Потом email (почта)
    if (!email.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(email);
      } else {
        flash(email);
      }
      // Используем .field-error элементы (как для полей дат)
      if (window.showFieldError) {
        window.showFieldError(email, 'Email is required');
      }
      email.focus();
      return;
    }
    // 6. Потом guests (количество гостей)
    if (!guests.value) {
      if (window.flashDateField) {
        window.flashDateField(guests);
      } else {
        flash(guests);
      }
      // Используем .field-error элементы (как для полей дат)
      if (window.showFieldError) {
        window.showFieldError(guests, 'At least 1 guest is required');
      }
      guests.focus();
      return;
    }
    // 7. Потом pets (наличие питомцев)
    if (!pets.value) {
      if (window.flashDateField) {
        window.flashDateField(pets);
      } else {
        flash(pets);
      }
      // Используем .field-error элементы (как для полей дат)
      if (window.showFieldError) {
        window.showFieldError(pets, 'Please select an option');
      }
      pets.focus();
      return;
    }

    // Валидация порядка дат и минимального интервала
    const inDate = parseLocalDate(checkin.value);
    const outDate = parseLocalDate(checkout.value);
    if (inDate && outDate) {
      // Проверка 1: дата выезда должна быть позже даты заезда
      if (outDate <= inDate) {
        // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
        checkout.setCustomValidity('');
        // Показываем ошибку в стиле .field-error (унифицированная система)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkin, DATE_RANGE_MSG);
        }
        // Мигаем оба поля красным
        if (window.flashDateField) {
          window.flashDateField(checkin);
          window.flashDateField(checkout);
        } else {
          flash(checkin);
          flash(checkout);
        }
        checkin.focus();
        return;
      }
      // Проверка 2: дата выезда должна быть минимум на 2 дня позже даты заезда
      const checkinPlusOne = new Date(inDate);
      checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
      if (outDate <= checkinPlusOne) {
        // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
        checkout.setCustomValidity('');
        // Показываем ошибку в стиле .field-error (унифицированная система)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkout, 'Дата выезда должна быть минимум на 2 дня позже даты заезда.');
        }
        // Мигаем оба поля красным
        if (window.flashDateField) {
          window.flashDateField(checkin);
          window.flashDateField(checkout);
        } else {
          flash(checkin);
          flash(checkout);
        }
        checkout.focus();
        return;
      }
      // Если все проверки пройдены, очищаем ошибки
      checkout.setCustomValidity('');
    }

    // Создаем бронирование через API
    if (window.BookingAPI && window.BookingAPI.handleBookingForm) {
      // Используем новый API
      window.BookingAPI.handleBookingForm(form).then((success) => {
        if (success) {
          // Успешное создание бронирования - редирект на страницу подтверждения
          // (редирект происходит в handleBookingForm)
        } else {
          // Ошибка создания бронирования - ошибки уже показаны в handleBookingForm
        }
      }).catch((error) => {
        console.error('Booking submission error:', error);
        alert('Failed to create booking. Please try again.');
      });
    } else {
      // Fallback на старое поведение, если booking.js не загружен
      document.dispatchEvent(new CustomEvent('btb:order:record', { detail: {
        kind: 'room',
        room: roomName,
        checkin: checkin.value || '',
        checkout: checkout.value || '',
        name: name.value || '',
        phone: phone.value || '',
        email: email.value || '',
        guests: guests.value || '',
        pets: pets.value || '',
        ts: Date.now(),
      }}));
      alert(`${roomName}: booking request sent!\nCheck‑in: ${checkin.value}\nCheck‑out: ${checkout.value}\nGuests: ${guests.value}`);
      form.reset();
      
      // Show wellness section automatically after successful booking (только для Basement)
      if (roomName === 'Basement — Queen') {
        showWellnessReminder();
        // Удаляем флаг скрытия, чтобы секция показывалась после бронирования
        localStorage.removeItem('btb_wellness_hidden');
      }
    }
  });
}

// Инициализация формы для Basement
const initBasementBooking = () => {
  const form = document.querySelector('form.booking-form[data-room="Basement — Queen"]');
  if (form) {
    initBookingForm(form, 'Basement — Queen');
  }
};

// Показ кастомного уведомления с более долгим временем показа
// Унифицированная функция для показа ошибок дат (использует .field-error систему)
// Оставлена для обратной совместимости, но теперь использует showFieldError внутри
function showDateErrorNotification(input, message, isFirstMessage = false) {
  // Используем унифицированную систему .field-error для всех полей
  // Это обеспечивает единообразное отображение ошибок для всех типов полей
  if (window.showFieldError) {
    window.showFieldError(input, message);
    return;
  }
  
  // Fallback: если showFieldError недоступна, используем старую систему
  // (для обратной совместимости, но это не должно происходить в нормальных условиях)
  console.warn('showFieldError not available, using fallback');
  
  // КРИТИЧНО: Для полей дат используется Flatpickr с altInput
  // Оригинальный input скрыт (1px x 1px), поэтому нужно использовать видимый altInput для позиционирования
  let targetInput = input;
  
  // Проверяем, используется ли Flatpickr с altInput
  if (typeof flatpickr !== 'undefined' && input._flatpickr) {
    const fpInstance = input._flatpickr;
    // Если есть altInput (видимое поле), используем его для позиционирования
    if (fpInstance.altInput) {
      targetInput = fpInstance.altInput;
    }
  }
  
  // Используем существующую функцию showValidationBubble, если доступна
  if (window.ValidationUtils && window.ValidationUtils.showBubble) {
    window.ValidationUtils.showBubble(targetInput, message);
    // Показываем уведомление на 4 секунды вместо стандартных
    setTimeout(() => {
      if (window.hideValidationBubble) {
        window.hideValidationBubble();
      }
    }, 4000);
    return;
  }
  
  // Fallback: используем локальную реализацию в стиле .btb-bubble
  // Удаляем предыдущее уведомление, если есть
  const existing = document.querySelector('#btb-bubble');
  if (existing) {
    existing.remove();
  }
  
  // Создаем уведомление в стиле .btb-bubble
  const bubble = document.createElement('div');
  bubble.id = 'btb-bubble';
  bubble.className = 'btb-bubble';
  
  const ic = document.createElement('div');
  ic.className = 'btb-ic';
  ic.textContent = '!';
  
  const msg = document.createElement('div');
  msg.className = 'btb-msg';
  msg.innerHTML = String(message || '').replace(/\n/g, '<br>');
  
  bubble.appendChild(ic);
  bubble.appendChild(msg);
  document.body.appendChild(bubble);
  
  // Позиционируем уведомление СРАЗУ ПОД видимым инпутом
  setTimeout(() => {
    const rect = targetInput.getBoundingClientRect();
    const windowWidth = window.innerWidth || document.documentElement.clientWidth;
    
    const bubbleWidth = bubble.offsetWidth || 360;
    const bubbleHeight = bubble.offsetHeight || 100;
    
    const top = rect.bottom + 8;
    const left = Math.min(windowWidth - bubbleWidth - 8, rect.right + 12);
    const finalLeft = Math.max(8, left);
    
    bubble.style.top = `${top}px`;
    bubble.style.left = `${finalLeft}px`;
  }, 0);
  
  // Показываем уведомление на 4 секунды
  setTimeout(() => {
    if (bubble.parentNode) {
      bubble.style.opacity = '0';
      bubble.style.transform = 'translateY(-10px)';
      setTimeout(() => {
        if (bubble.parentNode) {
          bubble.remove();
        }
      }, 300);
    }
  }, 4000);
  
  // Убираем уведомление при клике
  bubble.addEventListener('click', () => {
    if (bubble.parentNode) {
      bubble.style.opacity = '0';
      setTimeout(() => {
        if (bubble.parentNode) {
          bubble.remove();
        }
      }, 300);
    }
  });
}

// Блокировка занятых дат в date picker
async function initBlockedDatesForRoom(form, roomName) {
  try {
    // Получаем заблокированные даты (confirmed бронирования + ручные блокировки + Airbnb)
    const params = new URLSearchParams({
      action: 'get_blocked_dates',
      room_name: roomName
    });
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      console.warn('Failed to load blocked dates for room:', roomName);
      return;
    }
    
    const result = await response.json();
    let blockedDates = [];
    
    // Получаем ручные блокировки
    if (result.success && result.data?.blocked_dates) {
      blockedDates = result.data.blocked_dates.map(block => block.blocked_date);
    }
    
    // Получаем Airbnb заблокированные даты
    if (result.success && result.data?.airbnb_blocked_dates) {
      blockedDates = [...blockedDates, ...result.data.airbnb_blocked_dates];
    }
    
    // Получаем confirmed бронирования
    const bookingsParams = new URLSearchParams({
      action: 'get_bookings',
      room_name: roomName,
      status: 'confirmed'
    });
    
    const bookingsResponse = await fetch('api.php?' + bookingsParams.toString(), {
      method: 'GET'
    });
    
    if (bookingsResponse.ok) {
      const bookingsResult = await bookingsResponse.json();
      if (bookingsResult.success && bookingsResult.data?.bookings) {
        // Добавляем все даты из confirmed бронирований
        bookingsResult.data.bookings.forEach(booking => {
          const checkin = parseLocalDate(booking.checkin_date);
          const checkout = parseLocalDate(booking.checkout_date);
          if (checkin && checkout) {
            const currentDate = new Date(checkin);
            while (currentDate < checkout) {
              const dateString = formatDateString(currentDate);
              if (!blockedDates.includes(dateString)) {
                blockedDates.push(dateString);
              }
              currentDate.setDate(currentDate.getDate() + 1);
            }
          }
        });
      }
    }
    
    // Убираем дубликаты
    blockedDates = [...new Set(blockedDates)];
    
    // Блокируем даты в date inputs
    const checkinInput = form.querySelector('#checkin');
    const checkoutInput = form.querySelector('#checkout');
    
    if (checkinInput && checkoutInput) {
      // Проверяем, доступен ли Flatpickr
      const hasFlatpickr = typeof flatpickr !== 'undefined';
      
      if (hasFlatpickr) {
        // Используем Flatpickr для визуальной блокировки дат
        const blockedDatesArray = blockedDates.map(date => {
          const d = parseLocalDate(date);
          return d ? d.toISOString().split('T')[0] : null;
        }).filter(Boolean);
        
        // Отключаем enhanceDateInputs для этих полей (Flatpickr заменит нативный picker)
        checkinInput.dataset.enhancedDate = '1';
        checkoutInput.dataset.enhancedDate = '1';
        
        // Удаляем display proxy inputs от enhanceDateInputs, если они есть (чтобы избежать дублирования)
        const checkinDisplay = checkinInput.previousElementSibling && checkinInput.previousElementSibling.tagName === 'INPUT' && checkinInput.previousElementSibling.readOnly ? checkinInput.previousElementSibling : null;
        const checkoutDisplay = checkoutInput.previousElementSibling && checkoutInput.previousElementSibling.tagName === 'INPUT' && checkoutInput.previousElementSibling.readOnly ? checkoutInput.previousElementSibling : null;
        
        // Удаляем display proxy, чтобы избежать дублирования с Flatpickr
        if (checkinDisplay) {
          checkinDisplay.remove();
        }
        if (checkoutDisplay) {
          checkoutDisplay.remove();
        }
        
        // Восстанавливаем нормальное состояние inputs для Flatpickr
        // Flatpickr сам стилизует inputs, поэтому их нужно оставить видимыми
        checkinInput.style.position = '';
        checkinInput.style.opacity = '';
        checkinInput.style.pointerEvents = '';
        checkinInput.style.width = '';
        checkinInput.style.height = '';
        checkinInput.style.margin = '';
        checkinInput.style.visibility = '';
        checkinInput.style.clip = '';
        checkinInput.removeAttribute('readonly');
        
        checkoutInput.style.position = '';
        checkoutInput.style.opacity = '';
        checkoutInput.style.pointerEvents = '';
        checkoutInput.style.width = '';
        checkoutInput.style.height = '';
        checkoutInput.style.margin = '';
        checkoutInput.style.visibility = '';
        checkoutInput.style.clip = '';
        checkoutInput.removeAttribute('readonly');
        
        // Объявляем переменные для Flatpickr экземпляров в области видимости функции
        let fpCheckin = null;
        let fpCheckout = null;
        
        // Функция для получения дат, которые нужно заблокировать для checkout (до checkin + 1 день)
        const getDisabledDatesForCheckout = () => {
          const disabledDates = [...blockedDatesArray];
          
          // Если выбрана дата checkin, блокируем все даты до checkin + 1 день
          if (checkinInput.value) {
            const checkinDate = parseLocalDate(checkinInput.value);
            if (checkinDate) {
              // Блокируем все даты до checkin включительно
              const today = new Date();
              today.setHours(0, 0, 0, 0);
              let currentDate = new Date(today);
              
              // Блокируем все даты до checkin
              while (currentDate <= checkinDate) {
                const dateStr = formatDateString(currentDate);
                if (!disabledDates.includes(dateStr)) {
                  disabledDates.push(dateStr);
                }
                currentDate.setDate(currentDate.getDate() + 1);
              }
              
              // Блокируем checkin + 1 день
              const checkinPlusOne = new Date(checkinDate);
              checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
              const checkinPlusOneStr = formatDateString(checkinPlusOne);
              if (!disabledDates.includes(checkinPlusOneStr)) {
                disabledDates.push(checkinPlusOneStr);
              }
            }
          }
          
          return disabledDates;
        };
        
        // Инициализируем Flatpickr для check-in
        if (!checkinInput.dataset.flatpickrInitialized) {
          // Убеждаемся, что тип input остается "date" для HTML5 валидации
          checkinInput.type = 'date';
          
          fpCheckin = flatpickr(checkinInput, {
            dateFormat: 'Y-m-d',
            disable: blockedDatesArray,
            minDate: 'today',
            allowInput: false, // Отключаем прямой ввод в input
            clickOpens: true, // Открываем календарь при клике
            altInput: true, // Используем альтернативный input для отображения с placeholder
            altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
            placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                longhand: ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье']
              },
              months: {
                shorthand: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                longhand: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь']
              }
            },
            onReady: function(selectedDates, dateStr, instance) {
              // Убеждаемся, что родительский контейнер имеет position: relative
              const parent = instance.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // Скрываем оригинальный input, так как используем altInput для отображения
              // Убираем его далеко в сторону, чтобы он не перехватывал клики
              if (instance.input) {
                instance.input.style.position = 'absolute';
                instance.input.style.opacity = '0';
                instance.input.style.width = '0';
                instance.input.style.height = '0';
                instance.input.style.padding = '0';
                instance.input.style.margin = '0';
                instance.input.style.border = 'none';
                instance.input.style.pointerEvents = 'none';
                instance.input.style.left = '-9999px';
                instance.input.style.top = '-9999px';
                instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
              }
              
              // Убеждаемся, что altInput имеет правильный размер и кликабельную область
              if (instance.altInput) {
                instance.altInput.style.width = '100%';
                instance.altInput.style.cursor = 'pointer';
                // Устанавливаем placeholder после полной инициализации
                if (!instance.altInput.value) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                }
              }
            },
            onChange: function(selectedDates, dateStr, instance) {
              // Проверяем, не заблокирована ли выбранная дата
              if (dateStr && blockedDatesArray.includes(dateStr)) {
                instance.clear();
                checkinInput.value = '';
                // Восстанавливаем placeholder после очистки
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                // Показываем ошибку в стиле .field-error (как для обычных полей)
                if (window.showDateFieldError) {
                  window.showDateFieldError(checkinInput, 'Эта дата занята. Пожалуйста, выберите другую дату.');
                }
                flashDateField(checkinInput);
                return;
              }
              // Убеждаемся, что значение реального input обновлено
              if (dateStr) {
                checkinInput.value = dateStr;
                // Убеждаемся, что тип остается "date"
                checkinInput.type = 'date';
                // Очищаем ошибку, если дата выбрана корректно
                if (window.clearDateFieldError) {
                  window.clearDateFieldError(checkinInput);
                }
                // Вызываем события для валидации формы
                checkinInput.dispatchEvent(new Event('input', { bubbles: true }));
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                
                // Обновляем заблокированные даты для checkout при изменении checkin
                if (fpCheckout) {
                  const updatedDisabledDates = getDisabledDatesForCheckout();
                  fpCheckout.set('disable', updatedDisabledDates);
                  
                  // Если checkout уже выбран и он стал невалидным, показываем уведомление, но не очищаем
                  // Пользователь сам исправит дату выезда
                  if (checkoutInput.value) {
                    const checkoutDate = parseLocalDate(checkoutInput.value);
                    const checkinDate = parseLocalDate(dateStr);
                    if (checkinDate && checkoutDate) {
                      // Проверяем, не выбран ли checkin позже checkout
                      if (checkinDate >= checkoutDate) {
                        // Не очищаем checkout - пусть пользователь сам исправляет
                        // Показываем ошибку в стиле .field-error (как для обычных полей)
                        if (window.showDateFieldError) {
                          window.showDateFieldError(checkinInput, 'Check-out cannot be earlier than Check-in.\nPlease select a later date.');
                        }
                        // Мигаем оба поля красным
                        if (window.flashDateField) {
                          window.flashDateField(checkinInput);
                          window.flashDateField(checkoutInput);
                        } else {
                          flashDateField(checkinInput);
                        }
                      } else {
                        const checkinPlusOne = new Date(checkinDate);
                        checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
                        if (checkoutDate <= checkinPlusOne) {
                          // Не очищаем checkout - пусть пользователь сам исправляет
                          // Показываем ошибку в стиле .field-error (как для обычных полей)
                          if (window.showDateFieldError) {
                            window.showDateFieldError(checkoutInput, 'Дата выезда должна быть минимум на 2 дня позже даты заезда.');
                          }
                          // Мигаем оба поля красным
                          if (window.flashDateField) {
                            window.flashDateField(checkinInput);
                            window.flashDateField(checkoutInput);
                          } else {
                            flashDateField(checkoutInput);
                          }
                        }
                      }
                    }
                  }
                }
              } else {
                checkinInput.value = '';
                // Восстанавливаем placeholder если значение пустое
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Обновляем заблокированные даты для checkout при очистке checkin
                if (fpCheckout) {
                  fpCheckout.set('disable', blockedDatesArray);
                }
              }
            }
          });
          checkinInput.dataset.flatpickrInitialized = '1';
          
          // Скрываем оригинальный input сразу после инициализации
          setTimeout(() => {
            if (fpCheckin.input && fpCheckin.altInput) {
              // Убеждаемся, что родительский контейнер имеет position: relative
              const parent = fpCheckin.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
              fpCheckin.input.style.position = 'absolute';
              fpCheckin.input.style.opacity = '0';
              fpCheckin.input.style.width = '0';
              fpCheckin.input.style.height = '0';
              fpCheckin.input.style.padding = '0';
              fpCheckin.input.style.margin = '0';
              fpCheckin.input.style.border = 'none';
              fpCheckin.input.style.pointerEvents = 'none';
              fpCheckin.input.style.left = '-9999px';
              fpCheckin.input.style.top = '-9999px';
              fpCheckin.input.style.visibility = 'visible';
              
              // Убеждаемся, что altInput имеет правильный размер и кликабельную область
              fpCheckin.altInput.style.width = '100%';
              fpCheckin.altInput.style.cursor = 'pointer';
              
              // Убеждаемся, что placeholder установлен в altInput после инициализации
              if (!fpCheckin.altInput.value) {
                fpCheckin.altInput.placeholder = 'dd.mm.yyyy';
              }
            }
          }, 50);
        }
        
        // Инициализируем Flatpickr для check-out
        if (!checkoutInput.dataset.flatpickrInitialized) {
          // Убеждаемся, что тип input остается "date" для HTML5 валидации
          checkoutInput.type = 'date';
          
          fpCheckout = flatpickr(checkoutInput, {
            dateFormat: 'Y-m-d',
            disable: getDisabledDatesForCheckout(),
            minDate: 'today',
            allowInput: false, // Отключаем прямой ввод в input
            clickOpens: true, // Открываем календарь при клике
            altInput: true, // Используем альтернативный input для отображения с placeholder
            altFormat: 'F j, Y', // Формат отображения даты (например: "November 7, 2025")
            placeholder: 'dd.mm.yyyy', // Плейсхолдер для altInput (соответствует формату браузера)
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                longhand: ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье']
              },
              months: {
                shorthand: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                longhand: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь']
              }
            },
            onReady: function(selectedDates, dateStr, instance) {
              // Убеждаемся, что родительский контейнер имеет position: relative
              const parent = instance.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // Скрываем оригинальный input, так как используем altInput для отображения
              // Убираем его далеко в сторону, чтобы он не перехватывал клики
              if (instance.input) {
                instance.input.style.position = 'absolute';
                instance.input.style.opacity = '0';
                instance.input.style.width = '0';
                instance.input.style.height = '0';
                instance.input.style.padding = '0';
                instance.input.style.margin = '0';
                instance.input.style.border = 'none';
                instance.input.style.pointerEvents = 'none';
                instance.input.style.left = '-9999px';
                instance.input.style.top = '-9999px';
                instance.input.style.visibility = 'visible'; // Видим для браузера, но невидим для пользователя
              }
              
              // Убеждаемся, что altInput имеет правильный размер и кликабельную область
              if (instance.altInput) {
                instance.altInput.style.width = '100%';
                instance.altInput.style.cursor = 'pointer';
                // Устанавливаем placeholder после полной инициализации
                if (!instance.altInput.value) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                }
              }
            },
            onOpen: function(selectedDates, dateStr, instance) {
              // Обновляем заблокированные даты при открытии календаря
              const updatedDisabledDates = getDisabledDatesForCheckout();
              instance.set('disable', updatedDisabledDates);
            },
            onChange: function(selectedDates, dateStr, instance) {
              // Получаем список заблокированных дат (включая даты до checkin + 1)
              const disabledDates = getDisabledDatesForCheckout();
              
              // Проверяем, не заблокирована ли выбранная дата
              if (dateStr && disabledDates.includes(dateStr)) {
                instance.clear();
                checkoutInput.value = '';
                // Восстанавливаем placeholder после очистки
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Определяем причину блокировки и показываем соответствующее уведомление
                let errorMessage = 'Эта дата занята. Пожалуйста, выберите другую дату.';
                if (checkinInput.value) {
                  const checkinDate = parseLocalDate(checkinInput.value);
                  const selectedDate = parseLocalDate(dateStr);
                  if (checkinDate && selectedDate) {
                    const checkinPlusOne = new Date(checkinDate);
                    checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
                    if (selectedDate <= checkinPlusOne) {
                      errorMessage = 'Дата выезда должна быть минимум на 2 дня позже даты заезда.';
                    }
                  }
                }
                
                // Показываем ошибку в стиле .field-error (как для обычных полей)
                if (window.showDateFieldError) {
                  window.showDateFieldError(checkoutInput, errorMessage);
                }
                flashDateField(checkoutInput);
                return;
              }
              // Убеждаемся, что значение реального input обновлено
              if (dateStr) {
                checkoutInput.value = dateStr;
                // Убеждаемся, что тип остается "date"
                checkoutInput.type = 'date';
                // Очищаем ошибку, если дата выбрана корректно
                if (window.clearDateFieldError) {
                  window.clearDateFieldError(checkoutInput);
                }
                // Вызываем события для валидации формы
                checkoutInput.dispatchEvent(new Event('input', { bubbles: true }));
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
              } else {
                checkoutInput.value = '';
                // Восстанавливаем placeholder если значение пустое
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
              }
            }
          });
          checkoutInput.dataset.flatpickrInitialized = '1';
          
          // Скрываем оригинальный input сразу после инициализации
          setTimeout(() => {
            if (fpCheckout.input && fpCheckout.altInput) {
              // Убеждаемся, что родительский контейнер имеет position: relative
              const parent = fpCheckout.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // Убираем скрытый input далеко в сторону, чтобы он не перехватывал клики
              fpCheckout.input.style.position = 'absolute';
              fpCheckout.input.style.opacity = '0';
              fpCheckout.input.style.width = '0';
              fpCheckout.input.style.height = '0';
              fpCheckout.input.style.padding = '0';
              fpCheckout.input.style.margin = '0';
              fpCheckout.input.style.border = 'none';
              fpCheckout.input.style.pointerEvents = 'none';
              fpCheckout.input.style.left = '-9999px';
              fpCheckout.input.style.top = '-9999px';
              fpCheckout.input.style.visibility = 'visible';
              
              // Убеждаемся, что altInput имеет правильный размер и кликабельную область
              fpCheckout.altInput.style.width = '100%';
              fpCheckout.altInput.style.cursor = 'pointer';
            }
          }, 50);
        }
      } else {
        // Fallback: используем стандартную валидацию с улучшенным уведомлением
        const validateDateSelection = (input, dateValue) => {
          if (!dateValue) return true;
          const dateStr = dateValue;
          if (blockedDates.includes(dateStr)) {
            // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
            input.setCustomValidity('');
            input.value = '';
            return false;
          }
          input.setCustomValidity('');
          return true;
        };
        
        checkinInput.addEventListener('change', (e) => {
          if (!validateDateSelection(checkinInput, e.target.value)) {
            e.target.value = '';
            const display = checkinInput.previousElementSibling;
            if (display && display.tagName === 'INPUT') {
              display.value = '';
            }
          }
        });
        
        checkoutInput.addEventListener('change', (e) => {
          if (!validateDateSelection(checkoutInput, e.target.value)) {
            e.target.value = '';
            const display = checkoutInput.previousElementSibling;
            if (display && display.tagName === 'INPUT') {
              display.value = '';
            }
          }
        });
      }
      
      console.log(`Blocked dates initialized for ${roomName}:`, blockedDates.length, 'dates');
    }
  } catch (error) {
    console.error('Error initializing blocked dates:', error);
  }
}

// Форматирование даты в YYYY-MM-DD
function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Инициализация форм для остальных комнат
const initOtherRoomWellness = () => {
  const forms = document.querySelectorAll('form.booking-form[data-custom-handler]:not([data-room="Basement — Queen"])');
  forms.forEach(form => {
    const roomName = form.getAttribute('data-room');
    if (roomName) {
      initBookingForm(form, roomName);
    }
  });
};

// Min dates default to today
const setMinDates = () => {
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  const iso = `${yyyy}-${mm}-${dd}`;
  document.querySelectorAll('input[type="date"]').forEach(i => {
    i.min = iso;
  });
};

// Restrict time inputs to 15-minute steps (00, 15, 30, 45)
function initDateTimeUX() {
  configureTimeInputs(document);
  enhanceDateInputs(document);
  
  // Apply time enhancement to all time inputs except massage form
  const allTimeInputs = document.querySelectorAll('input[type="time"]');
  allTimeInputs.forEach(input => {
    const massageForm = input.closest('#massage-form');
    if (!massageForm) {
      enhanceTimeInputs(input.parentElement);
    }
  });
}

// Initialize massage form with time restrictions (9:00 - 21:00)
function initMassageTimeRestrictions() {
  const massageForm = document.querySelector('#massage-form');
  if (massageForm) {
    const timeInput = massageForm.querySelector('input[name="time"]');
    if (timeInput) {
      // Apply time restrictions for massage (9:00 - 21:00) with 30-minute intervals only
      enhanceTimeInputs(timeInput, { 
        minHour: 9, 
        maxHour: 21, 
        minuteOptions: ['00', '30'],
        defaultMinutes: '00'
      });
    }
  }
}

// Initialize clickable massage options
function initClickableMassageOptions() {
  const massageOptions = document.querySelectorAll('.massage-list li');
  const form = document.querySelector('#massage-form');
  
  if (!form) return;
  
  const typeSelect = form.querySelector('select#type');
  const durationSelect = form.querySelector('select#duration');
  
  massageOptions.forEach(option => {
    option.addEventListener('click', () => {
      // Extract massage type and duration from the clicked option
      const card = option.closest('.card-massage');
      const massageType = card.querySelector('h2').textContent;
      const durationText = option.textContent;
      
      // Extract duration value (e.g., "60 minutes" -> "60")
      const durationMatch = durationText.match(/(\d+)\s*minutes?/);
      const duration = durationMatch ? durationMatch[1] : '';
      
      // Set form values
      if (typeSelect) {
        typeSelect.value = massageType;
        typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      
      if (durationSelect && duration) {
        durationSelect.value = duration;
      }
      
      // Smooth scroll to form
      form.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'start' 
      });
      
      // Add visual feedback
      option.style.background = 'var(--brand)';
      option.style.color = 'white';
      setTimeout(() => {
        option.style.background = '';
        option.style.color = '';
      }, 1000);
    });
  });
}

// Replace native date inputs with a text display that shows e.g. "August 26, 2025"
function enhanceDateInputs(root) {
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const formatIso = (iso) => {
    if (!iso) return '';
    const d = parseLocalDate(iso);
    if (!d || Number.isNaN(d.getTime())) return '';
    return `${monthNames[d.getMonth()]} ${String(d.getDate()).padStart(2,'0')}, ${d.getFullYear()}`;
  };
  const dateInputs = Array.from(root.querySelectorAll('input[type="date"]')).filter(i => !i.dataset.enhancedDate);
  dateInputs.forEach(real => {
    real.dataset.enhancedDate = '1';
    const display = document.createElement('input');
    display.type = 'text';
    display.value = formatIso(real.value);
    display.placeholder = 'dd.mm.yyyy';
    display.readOnly = true;
    
    // Убеждаемся, что родительский контейнер имеет position: relative для правильного позиционирования
    const parent = real.parentElement;
    if (parent && window.getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }
    
    // Insert display before real; keep real hidden but present for JS/submit
    // Скрываем реальный input полностью, чтобы он не перехватывал клики
    real.style.position = 'absolute';
    real.style.opacity = '0';
    real.style.pointerEvents = 'none';
    real.style.width = '0';
    real.style.height = '0';
    real.style.margin = '0';
    real.style.padding = '0';
    real.style.border = 'none';
    real.style.left = '-9999px'; // Убираем далеко в сторону, чтобы не мешал
    real.style.top = '-9999px';
    
    // Убеждаемся, что визуальный display input имеет правильный размер и кликабельную область
    display.style.width = '100%';
    display.style.cursor = 'pointer';
    
    parent.insertBefore(display, real);
    const openPicker = () => {
      try { if (typeof real.showPicker === 'function') real.showPicker(); else real.click(); } catch (_) { real.focus(); real.click(); }
    };
    display.addEventListener('click', openPicker);
    display.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); } });
    const sync = () => { display.value = formatIso(real.value); };
    real.addEventListener('change', sync);
    sync();
  });
}

// expose for pages that render dynamic date inputs (order edit mode)
window.enhanceDateInputs = enhanceDateInputs;

// Configure time inputs to 15-minute steps and snap invalid values
function configureTimeInputs(root) {
  const snap = (input) => {
    if (!input.value) return;
    const parts = input.value.split(':');
    if (parts.length < 2) return;
    const h = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    if (Number.isNaN(h) || Number.isNaN(m)) return;
    const total = h * 60 + m;
    const snapped = Math.round(total / 15) * 15;
    const hh = String(Math.floor((snapped % 1440) / 60)).padStart(2, '0');
    const mm = String(snapped % 60).padStart(2, '0');
    input.value = `${hh}:${mm}`;
  };
  root.querySelectorAll('input[type="time"]').forEach(t => {
    if (t.dataset.timeEnhanced === '1') return;
    t.dataset.timeEnhanced = '1';
    t.step = 900; // 15 minutes
    snap(t);
    t.addEventListener('change', () => snap(t));
    t.addEventListener('blur', () => snap(t));
  });
}

window.configureTimeInputs = configureTimeInputs;

// Replace native time inputs UI with hour/minute selects (minutes: 00/15/30/45)
function enhanceTimeInputs(root, options = {}) {
  const { 
    minHour = 0, 
    maxHour = 23, 
    minuteOptions = ['00','15','30','45'],
    defaultMinutes = null
  } = options;
  // Handle both single elements and containers with multiple time inputs
  const timeInputs = root.tagName === 'INPUT' && root.type === 'time' 
    ? [root] 
    : Array.from(root.querySelectorAll('input[type="time"]'));
  const notEnhanced = timeInputs.filter(i => !i.dataset.enhancedTime);
  notEnhanced.forEach(real => {
    real.dataset.enhancedTime = '1';
    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.gap = '8px';
    const hh = document.createElement('select');
    const mm = document.createElement('select');
    // placeholders
    const phH = document.createElement('option'); phH.value = ''; phH.textContent = '—'; phH.selected = true; phH.disabled = true; phH.hidden = true; hh.appendChild(phH);
    const phM = document.createElement('option'); phM.value = ''; phM.textContent = '—'; phM.selected = true; phM.disabled = true; phM.hidden = true; mm.appendChild(phM);
    for (let h = minHour; h <= maxHour; h++) {
      const opt = document.createElement('option');
      opt.value = String(h).padStart(2, '0');
      opt.textContent = opt.value;
      hh.appendChild(opt);
    }
    minuteOptions.forEach(m => {
      const opt = document.createElement('option'); opt.value = m; opt.textContent = m; mm.appendChild(opt);
    });
    const setFromReal = () => {
      const [h, m] = (real.value || '').split(':');
      if (h) hh.value = h.padStart(2, '0');
      if (m) {
        // snap to nearest allowed minute
        const allowed = minuteOptions.map(x => parseInt(x, 10));
        const mv = parseInt(m, 10);
        const snapped = allowed.reduce((prev, curr) => 
          Math.abs(curr - mv) < Math.abs(prev - mv) ? curr : prev
        );
        mm.value = String(snapped).padStart(2,'0');
      } else if (defaultMinutes) {
        // Set default minutes if no value and default is specified
        mm.value = defaultMinutes;
      }
    };
    const syncReal = () => {
      if (hh.value && mm.value) {
        real.value = `${hh.value}:${mm.value}`;
        real.dispatchEvent(new Event('change', { bubbles: true }));
      } else {
        real.value = '';
      }
    };
    setFromReal();
    hh.addEventListener('change', syncReal);
    mm.addEventListener('change', syncReal);
    // Hide real input but keep it for submission
    real.style.position = 'absolute';
    real.style.opacity = '0';
    real.style.pointerEvents = 'none';
    real.style.width = '0';
    real.style.height = '0';
    real.style.margin = '0';
    // Insert UI before real
    wrap.appendChild(hh); wrap.appendChild(mm);
    real.parentElement.insertBefore(wrap, real);
    
    // For massage form, set default minutes if specified
    if (defaultMinutes && !real.value) {
      mm.value = defaultMinutes;
      syncReal();
    }
  });
}

window.enhanceTimeInputs = enhanceTimeInputs;

// Utility: enforce that checkout cannot be earlier than checkin, and update min attribute
const DATE_RANGE_MSG = 'Check‑out cannot be earlier than Check‑in.\nPlease select a later date.';
function parseLocalDate(iso) {
  if (!iso) return null;
  const parts = String(iso).split('-');
  if (parts.length !== 3) return null;
  const y = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10) - 1;
  const d = parseInt(parts[2], 10);
  return new Date(y, m, d);
}
function attachCheckinCheckoutConstraint(container, checkinSelector, checkoutSelector) {
  try {
    const checkin = container.querySelector(checkinSelector);
    const checkout = container.querySelector(checkoutSelector);
    if (!checkin || !checkout) return;
    const flash = (el) => flashDateField(el);
    const syncMin = () => {
      if (!checkin.value) {
        // Default: checkout cannot be today or earlier; set to tomorrow
        const t = new Date();
        const next = new Date(t.getFullYear(), t.getMonth(), t.getDate() + 1);
        const yyyy = next.getFullYear();
        const mm = String(next.getMonth() + 1).padStart(2, '0');
        const dd = String(next.getDate()).padStart(2, '0');
        checkout.min = `${yyyy}-${mm}-${dd}`;
        return;
      }
      // require checkout strictly after checkin
      const d = parseLocalDate(checkin.value);
      const next = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
      const yyyy = next.getFullYear();
      const mm = String(next.getMonth() + 1).padStart(2, '0');
      const dd = String(next.getDate()).padStart(2, '0');
      checkout.min = `${yyyy}-${mm}-${dd}`;
      // if currently invalid, notify instead of auto-shifting
      if (checkout.value) {
        const out = parseLocalDate(checkout.value);
        if (out <= d) {
          // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
          checkout.setCustomValidity('');
          // Для полей дат используем .field-error элементы (унифицированная система)
          if (window.showDateFieldError) {
            window.showDateFieldError(checkout, DATE_RANGE_MSG);
          }
          flashDateField(checkout);
          checkout.focus();
        }
      }
    };
    const validateCheckout = () => {
      if (!checkin.value || !checkout.value) return;
      const dIn = parseLocalDate(checkin.value);
      const dOut = parseLocalDate(checkout.value);
      if (dOut <= dIn) {
        // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
        checkout.setCustomValidity('');
        // Для полей дат используем .field-error элементы (унифицированная система)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkout, DATE_RANGE_MSG);
        }
        flashDateField(checkout);
        checkout.focus();
      } else {
        checkout.setCustomValidity('');
        clearDateFieldFlash(checkout);
      }
    };
    syncMin();
    checkin.addEventListener('change', syncMin);
    // If user adjusts Check‑in after selecting Check‑out, validate and flash tooltip
    checkin.addEventListener('change', () => {
      if (!checkout.value) return;
      const dIn = parseLocalDate(checkin.value);
      const dOut = parseLocalDate(checkout.value);
      if (dIn && dOut && dOut <= dIn) {
        // Очищаем setCustomValidity, чтобы не вызывать HTML5 валидацию
        checkout.setCustomValidity('');
        // Для полей дат используем .field-error элементы (унифицированная система)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkout, DATE_RANGE_MSG);
        }
        flashDateField(checkout);
      } else {
        checkout.setCustomValidity('');
        clearDateFieldFlash(checkout);
      }
    });
    checkout.addEventListener('change', validateCheckout);
  } catch (_) {}
}

// Expose for order.html edit mode (custom bubble styling, independent from room pages)
window.applyCheckinCheckoutConstraint = function(root) {
  if (!root) return;
  const dates = root.querySelectorAll('input[type="date"]');
  if (dates.length < 2) return;
  const checkin = dates[0];
  const checkout = dates[1];
  const flash = (el) => flashDateField(el);

  // Disallow past dates for Check‑in (today allowed)
  const setMinForCheckin = () => {
    const t = new Date();
    const yyyy = t.getFullYear();
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const dd = String(t.getDate()).padStart(2, '0');
    checkin.min = `${yyyy}-${mm}-${dd}`;
  };
  setMinForCheckin();

  const setMinForCheckout = () => {
    if (!checkin.value) { checkout.min = ''; return; }
    const d = parseLocalDate(checkin.value);
    const next = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
    const yyyy = next.getFullYear();
    const mm = String(next.getMonth() + 1).padStart(2, '0');
    const dd = String(next.getDate()).padStart(2, '0');
    checkout.min = `${yyyy}-${mm}-${dd}`;
  };

  const showBubble = () => {
    // Используем унифицированную систему .field-error вместо .btb-bubble
    if (window.showDateFieldError) {
      window.showDateFieldError(checkout, DATE_RANGE_MSG);
    }
  };
  const hideBubble = () => { 
    if (window.clearDateFieldError) {
      window.clearDateFieldError(checkout);
    }
  };

  const validate = () => {
    if (!checkin.value || !checkout.value) return;
    const dIn = parseLocalDate(checkin.value);
    const dOut = parseLocalDate(checkout.value);
    if (dOut <= dIn) {
      hideBubble();
      showBubble();
      flash(checkout);
      checkout.focus();
    } else {
      hideBubble();
      clearDateFieldFlash(checkout);
    }
  };

  setMinForCheckout();
  checkin.addEventListener('change', () => { setMinForCheckout(); validate(); });
  checkout.addEventListener('change', validate);
};

// Darken hero on scroll
const initHeroDarken = () => {
  const overlay = document.getElementById('heroOverlay');
  if (!overlay) return;
  const onScroll = () => {
    const hero = document.querySelector('.hero');
    if (!hero) return;
    const max = hero.offsetHeight || window.innerHeight;
    const y = Math.min(window.scrollY, max);
    const t = Math.max(0, Math.min(1, y / max));
    const darkness = 0 + t * 0.55; // up to 0.55 opacity
    overlay.style.setProperty('--hero-darkness', String(darkness));
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
};

// Resolve local gallery images and card images with unknown extension/base
const resolveImages = () => {
  const imgs = document.querySelectorAll('img[data-src-base]');
  const exts = ['.jpg', '.jpeg', '.JPG', '.JPEG'];
  imgs.forEach(img => {
    const baseAttr = img.getAttribute('data-src-base');
    const bases = baseAttr.split('|');
    let resolved = false;
    for (const base of bases) {
      if (resolved) break;
      for (const ext of exts) {
        if (resolved) break;
        const testSrc = `${base}${ext}?v=4`;
        const test = new Image();
        test.onload = () => { if (!resolved) { img.src = testSrc; resolved = true; } };
        test.onerror = () => {};
        test.src = testSrc;
      }
    }
  });
};

const resolveGalleryImages = resolveImages;

document.addEventListener('DOMContentLoaded', () => {
  // Глобально отключаем HTML5 валидацию для всех форм на сайте
  // Это предотвращает появление серых подсказок браузера
  document.querySelectorAll('form').forEach(form => {
    form.setAttribute('novalidate', 'novalidate');
    // Дополнительно: перехватываем submit на раннем этапе, чтобы предотвратить HTML5 валидацию
    form.addEventListener('submit', (e) => {
      // Если форма еще не имеет обработчика submit, предотвращаем стандартное поведение
      if (!form.dataset.hasSubmitHandler) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    }, true); // Используем capture phase для раннего перехвата
  });
  
  // Дополнительно: отключаем HTML5 валидацию для всех input с атрибутом required
  // Это предотвращает появление серых подсказок при попытке submit
  document.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
    // Удаляем атрибут required, но сохраняем его в data-required для нашей валидации
    if (!input.dataset.required) {
      input.dataset.required = 'true';
      input.removeAttribute('required');
    }
  });
  
  revealElements();
  setYear();
  initBookingForms();
  initMassageForm();
  setMinDates();
  initHeroDarken();
  resolveImages();
  initBasementBooking();
  initOtherRoomWellness();
  // initOrderIndicator(); // Отключено - иконка домика больше не показывается
  
  // Скрываем иконку домика на страницах комнат, если она была создана
  hideOrderIndicatorOnRoomPages();
  
  initDateTimeUX();
  initMassageTimeRestrictions();
  initClickableMassageOptions();
  
  // Check and show wellness section if user has seen it before
  checkAndShowWellnessSection();
  
  // Initialize authentication system if auth.js is loaded
  if (typeof AuthSystem !== 'undefined') {
    window.authSystem = new AuthSystem();
  }
});

// Persist lightweight orders and show indicator
function initOrderIndicator() {
  // inject order indicator into body
  const indicator = document.createElement('a');
  indicator.href = 'order.html';
  indicator.className = 'order-indicator';
  indicator.setAttribute('aria-label', 'View your order');
  indicator.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  document.body.appendChild(indicator);
  
  // inject message indicator into body
  const messageIndicator = document.createElement('a');
  messageIndicator.href = 'messages.html';
  messageIndicator.className = 'message-indicator';
  messageIndicator.setAttribute('aria-label', 'View messages');
  messageIndicator.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  document.body.appendChild(messageIndicator);
  
  migrateSingleOrderToArray();
  indicator.style.display = getOrders().length > 0 ? 'flex' : 'none';
  
  // Check for unread messages and show indicator
  checkForUnreadMessages(messageIndicator);

  // Listen for successful booking events
  document.addEventListener('btb:order:record', (ev) => {
    try {
      const orders = getOrders();
      orders.push({ id: `ord_${Date.now()}_${Math.random().toString(36).slice(2,8)}`, ...ev.detail });
      setOrders(orders);
      indicator.style.display = 'flex';
    } catch (_) {}
  });
  
  // Listen for new message events
  document.addEventListener('btb:message:received', (ev) => {
    try {
      messageIndicator.style.display = 'flex';
    } catch (_) {}
  });
}

// Check for unread messages and show indicator
function checkForUnreadMessages(messageIndicator) {
  try {
    const messages = JSON.parse(localStorage.getItem('btb_messages') || '[]');
    const hasUnreadMessages = messages.some(msg => msg && msg.from === 'host' && !msg.read);
    messageIndicator.style.display = hasUnreadMessages ? 'flex' : 'none';
  } catch (_) {
    messageIndicator.style.display = 'none';
  }
}

function getOrders() {
  try {
    const raw = localStorage.getItem('btb_orders');
    if (!raw) return [];
    const arr = JSON.parse(raw);
    return Array.isArray(arr) ? arr : [];
  } catch (_) { return []; }
}

function setOrders(orders) {
  try { localStorage.setItem('btb_orders', JSON.stringify(orders)); } catch (_) {}
}

function hasOrder(kind) {
  try {
    const raw = localStorage.getItem('btb_orders');
    const arr = JSON.parse(raw || '[]');
    return Array.isArray(arr) && arr.some(o => o && o.kind === kind);
  } catch (_) { return false; }
}

// Prefill name/email/phone from any previous order
function prefillContact(root) {
  try {
    const raw = localStorage.getItem('btb_orders');
    const arr = JSON.parse(raw || '[]');
    if (!Array.isArray(arr) || arr.length === 0) return;
    // take most recent order with these fields
    const last = [...arr].reverse().find(o => (o.name || o.email || o.phone));
    if (!last) return;
    const name = root.querySelector('input[name="name"], #name');
    const email = root.querySelector('input[name="email"], #email');
    const phone = root.querySelector('input[name="phone"], #phone');
    if (name && !name.value) name.value = last.name || '';
    if (email && !email.value) email.value = last.email || '';
    if (phone && !phone.value) phone.value = last.phone || '';
  } catch (_) {}
}

function migrateSingleOrderToArray() {
  const legacy = localStorage.getItem('btb_order');
  if (!legacy) return;
  try {
    const obj = JSON.parse(legacy);
    const arr = getOrders();
    arr.push({ id: `ord_${Date.now()}_${Math.random().toString(36).slice(2,8)}`, ...obj });
    setOrders(arr);
    localStorage.removeItem('btb_order');
  } catch (_) {}
}

// Check if wellness section should be shown based on localStorage
// По умолчанию секция показывается на всех страницах комнат
// Скрывается только если пользователь явно её закрыл
function checkAndShowWellnessSection() {
  try {
    const section = document.getElementById('wellness-section');
    if (section) {
      // Проверяем, была ли секция скрыта пользователем
      const wellnessHidden = localStorage.getItem('btb_wellness_hidden');
      if (wellnessHidden !== '1') {
        // Показываем секцию по умолчанию, если она не была скрыта
        section.style.display = 'block';
      } else {
        // Скрываем секцию, если пользователь её закрыл
        section.style.display = 'none';
      }
    }
  } catch (_) {}
}

// Hide wellness section and remember user's choice
function hideWellnessSection() {
  try {
    const section = document.getElementById('wellness-section');
    if (section) {
      section.style.display = 'none';
      // Устанавливаем флаг, что секция была скрыта пользователем
      localStorage.setItem('btb_wellness_hidden', '1');
    }
  } catch (_) {}
}

// Make function globally available
window.hideWellnessSection = hideWellnessSection;

// Function to update auth buttons
function updateAuthButtons() {
  try {
    if (window.authSystem && typeof window.authSystem.updateHeaderButtons === 'function') {
      window.authSystem.updateHeaderButtons();
    }
  } catch (_) {}
}

// Make function globally available
window.updateAuthButtons = updateAuthButtons;

// Theme management
class ThemeManager {
  constructor() {
    // Get theme that was already set by inline script in <head>
    // Inline script always sets the correct theme based on localStorage
    // We just read it and use it - don't override it
    const currentDataTheme = document.documentElement.getAttribute('data-theme');
    
    // Use the theme from inline script - it's always correct
    if (currentDataTheme === 'dark' || currentDataTheme === 'light') {
      this.currentTheme = currentDataTheme;
    } else {
      // Fallback only if inline script didn't work (shouldn't happen)
      this.currentTheme = 'dark';
      document.documentElement.setAttribute('data-theme', 'dark');
    }
    
    this.init();
  }

  init() {
    // Only update the button - theme is already set correctly by inline script
    this.updateThemeToggle(this.currentTheme);
    this.setupEventListeners();
  }

  setupEventListeners() {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
      themeToggle.addEventListener('click', () => {
        this.toggleTheme();
      });
    }
  }

  toggleTheme() {
    this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
    this.applyTheme(this.currentTheme);
    localStorage.setItem('btb_theme', this.currentTheme);
    localStorage.setItem('btb_theme_user', '1');
  }

  applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    this.updateThemeToggle(theme);
  }

  updateThemeToggle(theme) {
    const themeText = document.getElementById('theme-text');
    const themeIcon = document.querySelector('.theme-toggle-icon path');
    
    if (themeText) {
      // Show the opposite theme name - if current theme is 'dark', show 'Light' to switch to light
      // If current theme is 'light', show 'Dark' to switch to dark
      themeText.textContent = theme === 'dark' ? 'Light' : 'Dark';
    }
    
    if (themeIcon) {
      // Update icon based on what theme we'll switch TO, not current theme
      // If current theme is 'dark', show sun icon (to switch to light)
      // If current theme is 'light', show moon icon (to switch to dark)
      if (theme === 'dark') {
        // Sun icon for switching to light
        themeIcon.setAttribute('d', 'M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
      } else {
        // Moon icon for switching to dark
        themeIcon.setAttribute('d', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z');
      }
    }
  }
}

// Initialize theme manager
let themeManager;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Initialize theme manager
  themeManager = new ThemeManager();
  
  // Set year in footer
  setYear();
  
  // Initialize booking forms
  initBookingForms();
  
  // Check wellness section visibility
  checkAndShowWellnessSection();
  
  // Migrate legacy order format if needed
  migrateSingleOrderToArray();
  
  // Update auth buttons if auth system is available
  setTimeout(() => {
    if (window.authSystem && typeof window.authSystem.updateHeaderButtons === 'function') {
      window.authSystem.updateHeaderButtons();
    }
  }, 100);
  
  // Listen for auth status changes
  window.addEventListener('storage', (e) => {
    if (e.key === 'btb_auth_token' || e.key === 'btb_user_data') {
      setTimeout(() => {
        updateAuthButtons();
      }, 50);
    }
  });
  
  // Load floorplan data from admin
  loadFloorplanFromAdmin();
});

// Load floorplan data from admin and update content
async function loadFloorplanFromAdmin() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_floorplan');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (response.ok) {
      const result = await response.json();
      
      // Only update if we have actual data from database
      if (result.success && result.data) {
        const hasData = result.data.basementSubtitle || result.data.groundSubtitle || result.data.loftSubtitle;
        if (hasData) {
          updateFloorPlanContent(result.data);
        }
      }
    }
  } catch (error) {
    // Silently fail - site should show default content
    console.log('Floorplan API not available, using default content');
  }
}

// Helper function to update floorplan content on page
// OLD FUNCTION REMOVED - use updateFloorPlanContent() instead (uses universal updateImageElement function)

// Update floor plan content on the page
// Universal function to update images - all sections use HTML <img> elements
// NOTE: All images are JPG/PNG/GIF files displayed through HTML <img> elements
function updateImageElement(selector, imageUrl) {
  const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
  if (!element) {
    console.error('Image element not found:', selector);
    return false;
  }
  
  // All images are HTML <img> elements (displays JPG/PNG/GIF files)
  if (element.tagName === 'IMG') {
    element.src = imageUrl;
    element.srcset = imageUrl;
    return true;
  }
  
  console.error('Expected IMG element, got:', element.tagName);
  return false;
}

function updateFloorPlanContent(data) {
  try {
    console.log('Updating floor plan content with data:', data);
    
    // Update basement section
    const basementCards = document.querySelectorAll('.floor-card');
    console.log('Found floor cards:', basementCards.length);
    
    if (basementCards.length >= 1) {
      const basementSubtitle = basementCards[0].querySelector('.floor-sub');
      const basementDesc = basementCards[0].querySelector('.floor-desc');
      
      // Handle both camelCase and underscore formats
      const basementTitle = data.basementSubtitle || data.basement_subtitle || '';
      const basementDescription = data.basementDescription || data.basement_description || '';
      // Universal: use consistent field names (with fallback for compatibility)
      const basementImage = data.basementImageUrl || data.basement_image_url || '';
      
      if (basementTitle && basementSubtitle) {
        basementSubtitle.textContent = basementTitle;
        console.log('Updated basement subtitle');
      }
      if (basementDescription && basementDesc) {
        basementDesc.textContent = basementDescription;
        console.log('Updated basement description');
      }
      
      if (basementImage) {
        console.log('Updating basement image:', basementImage);
        const imageUrl = basementImage + '?v=' + Date.now();
        
        // Use universal function for basement
        const basementImg = basementCards[0].querySelector('.floor-photo');
        if (updateImageElement(basementImg, imageUrl)) {
          console.log('Updated basement image');
        }
        
        // Also update all <source> elements in the picture
        const sources = basementCards[0].querySelectorAll('source');
        sources.forEach(source => {
          source.srcset = imageUrl;
        });
        console.log('Updated basement sources:', sources.length);
      }
    }
    
    // Update ground floor section
    if (basementCards.length >= 2) {
      const groundSubtitle = basementCards[1].querySelector('.floor-sub');
      const groundDesc = basementCards[1].querySelector('.floor-desc');
      
      // Handle both camelCase and underscore formats
      const groundTitle = data.groundSubtitle || data.ground_subtitle || '';
      const groundDescription = data.groundDescription || data.ground_description || '';
      // Universal: use consistent field names (with fallback for compatibility)
      const groundQueenImage = data.groundQueenImage || data.ground_image_url || data.ground_queen_image || '';
      
      if (groundTitle && groundSubtitle) {
        groundSubtitle.textContent = groundTitle;
      }
      if (groundDescription && groundDesc) {
        groundDesc.textContent = groundDescription;
      }
      
      // Update ground floor image - use same universal function as basement
      if (groundQueenImage) {
        console.log('Updating ground floor image:', groundQueenImage);
        const imageUrl = groundQueenImage + '?v=' + Date.now();
        
        // Use same approach as basement - querySelector('.floor-photo')
        const groundImg = basementCards[1].querySelector('.floor-photo');
        if (updateImageElement(groundImg, imageUrl)) {
          console.log('Updated ground floor image');
        }
        
        // Also update all <source> elements in the picture
        const sources = basementCards[1].querySelectorAll('source');
        sources.forEach(source => {
          source.srcset = imageUrl;
        });
        console.log('Updated ground floor sources:', sources.length);
      }
    }
    
    // Update loft section
    if (basementCards.length >= 3) {
      const loftSubtitle = basementCards[2].querySelector('.floor-sub');
      const loftDesc = basementCards[2].querySelector('.floor-desc');
      
      // Handle both camelCase and underscore formats
      const loftTitle = data.loftSubtitle || data.loft_subtitle || '';
      const loftDescription = data.loftDescription || data.loft_description || '';
      const loftImage = data.loftImageUrl || data.loft_image_url || '';
      
      if (loftTitle && loftSubtitle) {
        loftSubtitle.textContent = loftTitle;
      }
      if (loftDescription && loftDesc) {
        loftDesc.textContent = loftDescription;
      }
      
      // Update loft image - use same universal function as basement
      if (loftImage) {
        console.log('Updating loft image:', loftImage);
        const imageUrl = loftImage + '?v=' + Date.now();
        
        // Use same approach as basement - querySelector('.floor-photo')
        const loftImg = basementCards[2].querySelector('.floor-photo');
        if (updateImageElement(loftImg, imageUrl)) {
          console.log('Updated loft image');
        }
        
        // Also update all <source> elements in the picture
        const sources = basementCards[2].querySelectorAll('source');
        sources.forEach(source => {
          source.srcset = imageUrl;
        });
        console.log('Updated loft sources:', sources.length);
      }
    }
  } catch (error) {
    console.error('Error updating floor plan content:', error);
  }
}

// Load floor plan data from storage/localStorage
function loadFloorPlanData() {
  try {
    console.log('Loading floor plan data...');
    
    // Try to get data from localStorage first (from admin panel)
    const storedData = localStorage.getItem('btb_floorplan_settings');
    if (storedData) {
      console.log('Found data in localStorage:', storedData);
      const data = JSON.parse(storedData);
      updateFloorPlanContent(data);
      return;
    }
    
    console.log('No localStorage data, fetching from API...');
    // If no localStorage data, try to fetch from API
    fetchFloorPlanFromAPI();
  } catch (error) {
    console.error('Error loading floor plan data:', error);
  }
}

// Fetch floor plan data from API
async function fetchFloorPlanFromAPI() {
  try {
    console.log('Fetching floor plan data from API...');
    const response = await fetch('api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'action=get_floorplan'
    });
    
    if (response.ok) {
      const text = await response.text();
      console.log('API response text:', text);
      
      try {
        const result = JSON.parse(text);
        console.log('Parsed API result:', result);
        
        if (result.success && result.data) {
          console.log('API data received:', result.data);
          updateFloorPlanContent(result.data);
          // Store in localStorage for future use
          localStorage.setItem('btb_floorplan_settings', JSON.stringify(result.data));
          console.log('Data saved to localStorage');
        } else {
          console.log('API returned no data or failed');
        }
      } catch (parseError) {
        console.error('Error parsing JSON response:', parseError);
        console.log('Response text:', text);
      }
    } else {
      console.error('HTTP error:', response.status, response.statusText);
    }
  } catch (error) {
    console.error('Error fetching floor plan data:', error);
  }
}

// Initialize floor plan data when DOM is ready - call SYNCHRONOUSLY to update before images load
function initFloorPlanData() {
  // Only load floor plan data on main site, not in admin
  if (!window.location.pathname.includes('admin')) {
    loadFloorPlanData();
  }
}

// Call immediately if DOM is already loaded, otherwise wait for DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initFloorPlanData);
} else {
  // DOM already loaded, call immediately
  initFloorPlanData();
}
