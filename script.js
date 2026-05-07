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
// CRITICAL: Flatpickr with altInput is used for date fields
// You need to flash the visible altInput, not the hidden input
function flashDateField(realInput) {
  if (!realInput) return;
  
  // Checking if Flatpickr is used with altInput
  if (typeof flatpickr !== 'undefined' && realInput._flatpickr) {
    const fpInstance = realInput._flatpickr;
    // If there is an altInput (visible field), flash it
    if (fpInstance.altInput) {
      flashInvalid(fpInstance.altInput);
    }
    // We also flash hidden input for browser validation
    flashInvalid(realInput);
  } else {
    // Fallback: using old logic for fields without Flatpickr
    flashInvalid(realInput);
    try {
      const proxy = realInput.previousElementSibling;
      if (proxy && proxy.tagName === 'INPUT') {
        flashInvalid(proxy);
      }
    } catch (_) {}
  }
}

// Show error for field in .field-error style (universal function for all fields)
function showFieldError(input, message) {
  if (!input || !message) return;
  
  // Find the visible field (altInput for Flatpickr or the field itself)
  let visibleField = input;
  if (typeof flatpickr !== 'undefined' && input._flatpickr) {
    const fpInstance = input._flatpickr;
    if (fpInstance.altInput) {
      visibleField = fpInstance.altInput;
    }
  }
  
  // Remove the previous error, if any
  const errorId = `error-${input.id || input.name || 'field'}`;
  const existingError = visibleField.parentNode?.querySelector(`#${errorId}`);
  if (existingError) {
    existingError.remove();
  }
  
  // Adding the invalid-field class to the visible field
  visibleField.classList.add('invalid-field');
  if (input !== visibleField) {
    input.classList.add('invalid-field');
  }
  
  // Create an error element
  const errorMsg = document.createElement('div');
  errorMsg.className = 'field-error';
  errorMsg.textContent = message;
  errorMsg.id = errorId;
  
  // Insert an error after the visible field
  if (visibleField.parentNode) {
    visibleField.parentNode.insertBefore(errorMsg, visibleField.nextSibling);
  }
}

// Show error for date field in .field-error style (as for regular fields)
function showDateFieldError(input, message) {
  showFieldError(input, message);
}

// Clear error for field (universal function for all fields)
function clearFieldError(input) {
  if (!input) return;
  
  // Find the visible field (altInput for Flatpickr or the field itself)
  let visibleField = input;
  if (typeof flatpickr !== 'undefined' && input._flatpickr) {
    const fpInstance = input._flatpickr;
    if (fpInstance.altInput) {
      visibleField = fpInstance.altInput;
    }
  }
  
  // Remove the error if there is one
  const errorId = `error-${input.id || input.name || 'field'}`;
  const existingError = visibleField.parentNode?.querySelector(`#${errorId}`);
  if (existingError) {
    existingError.remove();
  }
  
  // Removing the invalid-field and flash-invalid classes
  visibleField.classList.remove('invalid-field', 'flash-invalid');
  if (input !== visibleField) {
    input.classList.remove('invalid-field', 'flash-invalid');
  }
}

// Clear error for date field (alias for backwards compatibility)
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
  try {
    if (window.ValidationUtils && typeof window.ValidationUtils.hideBubble === 'function') {
      window.ValidationUtils.hideBubble();
    }
  } catch (_) {}
  const ex = document.getElementById('btb-bubble');
  if (ex && ex.parentNode) ex.parentNode.removeChild(ex);
  try {
    document.querySelectorAll('.btb-bubble').forEach((n) => {
      if (n && n.parentNode) n.parentNode.removeChild(n);
    });
  } catch (_) {}
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
    // Setting up error cleaning for all forms
    setupFieldErrorClearing(form);
    prefillContact(form);
    // Note that the form has a submit handler
    form.dataset.hasSubmitHandler = 'true';
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopImmediatePropagation();
      const checkinEl = form.querySelector('input[name="checkin"]');
      const checkoutEl = form.querySelector('input[name="checkout"]');
      const checkin = checkinEl ? checkinEl.value : '';
      const checkout = checkoutEl ? checkoutEl.value : '';
      if (!checkin || !checkout) {
        // For date fields we use custom hints (.field-error)
        // We don't use reportValidity() because it shows HTML5 browser validation
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
          // Clearing setCustomValidity so as not to trigger HTML5 validation
          checkoutEl.setCustomValidity('');
          // For date fields we use .field-error elements (unified system)
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
  const typeSel = form.querySelector('#type');
  const durationSel = form.querySelector('#duration');
  // const withRoomSel = form.querySelector('select#with-room'); // Field removed
  const reminder = document.getElementById('room-reminder');
  // Temporarily disable auto-show from localStorage; keep hidden until successful booking submit
  if (reminder) {
    reminder.style.display = 'none';
  }
  const setDurations = () => {
    if (!durationSel || durationSel.tagName !== 'SELECT' || !typeSel || typeSel.tagName !== 'SELECT') {
      return;
    }
    const type = typeSel.value;
    let options = [];
    if (type === 'Reiki Energy Healing') {
      options = [
        { v: '15', t: '15 minutes' },
        { v: '30', t: '30 minutes' },
      ];
    } else if (type === 'Sauna') {
      options = [
        { v: '60', t: '1 hour' },
      ];
      durationSel.disabled = true;
    } else {
      options = [
        { v: '60', t: '60 minutes' },
        { v: '90', t: '90 minutes' },
      ];
      durationSel.disabled = false;
    }
    durationSel.innerHTML = '';
    options.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.v; opt.textContent = o.t;
      durationSel.appendChild(opt);
    });
    // Auto-select first option for Sauna
    if (type === 'Sauna' && options.length > 0) {
      durationSel.value = options[0].v;
    }
  };
  if (typeSel && durationSel && typeSel.tagName === 'SELECT' && durationSel.tagName === 'SELECT') {
    typeSel.addEventListener('change', setDurations);
    setDurations();
  }
  form._setMassageDurations = setDurations;
  if (window.BookingAPI && typeof window.BookingAPI.renderMassageCartUI === 'function') {
    window.BookingAPI.renderMassageCartUI(form);
  }
  
  // Setting up error cleaning
  setupMassageFieldErrorClearing(form);
  
  // Note that the form has a submit handler
  form.dataset.hasSubmitHandler = 'true';
  
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    
    // Using the new massage form processing function
    if (window.BookingAPI && window.BookingAPI.handleMassageForm) {
      const success = await window.BookingAPI.handleMassageForm(form);
      if (success) {
        // Successful booking creation - the form has already been processed in handleMassageForm
      } else {
        // Error creating booking - errors already shown in handleMassageForm
      }
    } else {
      // Fallback to old behavior if booking.js is not loaded
      const dateEl = form.querySelector('input[name="date"]');
      const timeEl = form.querySelector('input[name="time"]');
      const nameEl = form.querySelector('input[name="name"]');
      const emailEl = form.querySelector('input[name="email"]');
      const phoneEl = form.querySelector('input[name="phone"]');
      const type = typeSel ? typeSel.value : '';
      const dur = durationSel ? durationSel.value : '';
      const withRoom = ''; // Field removed

      // Ordered validation with new tooltip style
      if (!type) {
        if (typeSel && typeSel.getAttribute('type') === 'hidden') {
          const scrollTo = document.getElementById('massage-form') || document.getElementById('book');
          if (scrollTo) {
            scrollTo.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          alert('Please add at least one service by tapping a price on a card above, then choose date and time.');
        } else if (typeSel) {
          if (window.showFieldError) window.showFieldError(typeSel, 'Massage type is required');
          typeSel.classList.add('flash-invalid');
          typeSel.focus();
        }
        return;
      }
      if (!dur) {
        if (durationSel && durationSel.getAttribute('type') === 'hidden') {
          const scrollTo = document.getElementById('massage-form') || document.getElementById('book');
          if (scrollTo) {
            scrollTo.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          alert('Please add at least one service by tapping a price on a card above, then choose date and time.');
        } else if (durationSel) {
          if (window.showFieldError) window.showFieldError(durationSel, 'Duration is required');
          durationSel.classList.add('flash-invalid');
          durationSel.focus();
        }
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
      // withRoom field removed - no validation needed
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

      // Show reminder after successful submit
      if (reminder && !hasOrder('room')) {
        localStorage.setItem('btb_room_reminder_shown', '1');
        reminder.style.display = 'block';
        reminder.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      alert(`Massage booking (${type}, ${dur} min) sent!\n${dateEl.value} at ${timeEl.value}. We will confirm by email.`);
      form.reset();
      setDurations();
    }
  });
};

// Setting up error clearing for the massage form
function setupMassageFieldErrorClearing(form) {
  if (!form) return;
  
  // Type field - clear the error when selecting a value (only for visible select)
  const typeSelect = form.querySelector('#type');
  if (typeSelect && typeSelect.tagName === 'SELECT') {
    typeSelect.addEventListener('change', () => {
      if (typeSelect.value && window.clearFieldError) {
        window.clearFieldError(typeSelect);
      }
    });
  }

  // Duration field - clear the error when selecting a value (only for visible select)
  const durationSelect = form.querySelector('#duration');
  if (durationSelect && durationSelect.tagName === 'SELECT') {
    durationSelect.addEventListener('change', () => {
      if (durationSelect.value && window.clearFieldError) {
        window.clearFieldError(durationSelect);
      }
    });
  }
  
  // Date field - clear the error when selecting the correct date
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
  
  // Time field - clear the error when choosing the correct time
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
  
  // Name field - clear the error when entering text
  const nameInput = form.querySelector('#name');
  if (nameInput) {
    nameInput.addEventListener('input', () => {
      if (nameInput.value.trim() && window.clearFieldError) {
        window.clearFieldError(nameInput);
      }
    });
  }
  
  // Email field - clear the error when entering the correct email
  const emailInput = form.querySelector('#email');
  if (emailInput) {
    emailInput.addEventListener('input', () => {
      const emailValue = emailInput.value.trim();
      if (emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue) && window.clearFieldError) {
        window.clearFieldError(emailInput);
      }
    });
  }
  
  // withRoom field removed - no event listener needed
  
  // Phone field - clear the error when entering the correct phone number
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

// A universal function for setting up automatic clearing of errors when filled in correctly
// Used for all room booking forms
function setupFieldErrorClearing(form) {
  if (!form) return;
  
    // Date fields - clear errors when selecting the correct date
    const checkinInput = form.querySelector('#checkin') || form.querySelector('[name="checkin"]');
    const checkoutInput = form.querySelector('#checkout') || form.querySelector('[name="checkout"]');
    
    if (checkinInput) {
      const clearCheckinError = () => {
        if (!checkinInput.value) return;
        
        // We check that the date is correct (not in the past and not blocked)
        const checkinDate = parseLocalDate(checkinInput.value);
        if (checkinDate) {
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          const checkinDateOnly = new Date(checkinDate.getFullYear(), checkinDate.getMonth(), checkinDate.getDate());
          const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
          
          // Clear the error only if the date is not in the past
          if (checkinDateOnly >= todayDateOnly && window.clearFieldError) {
            window.clearFieldError(checkinInput);
            
            // If check-out is already selected and becomes correct after changing check-in, clear its error
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
        
        // We check that the date is correct (later checkin + 1 day)
        const checkoutDate = parseLocalDate(checkoutInput.value);
        if (checkoutDate && checkinInput && checkinInput.value) {
          const checkinDate = parseLocalDate(checkinInput.value);
          if (checkinDate) {
            const checkinPlusOne = new Date(checkinDate);
            checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
            
            // Clear the error only if checkout > checkin + 1
            if (checkoutDate > checkinPlusOne && window.clearFieldError) {
              window.clearFieldError(checkoutInput);
            }
          }
        } else if (checkoutDate && window.clearFieldError) {
          // If checkin is not selected, clear the checkout error
          window.clearFieldError(checkoutInput);
        }
      };
      checkoutInput.addEventListener('input', clearCheckoutError);
      checkoutInput.addEventListener('change', clearCheckoutError);
    }
    
    // Name field - clear the error when entering text
    const nameInput = form.querySelector('#name') || form.querySelector('[name="name"]') || form.querySelector('[name="guest_name"]');
    if (nameInput) {
      nameInput.addEventListener('input', () => {
        if (nameInput.value.trim() && window.clearFieldError) {
          window.clearFieldError(nameInput);
        }
      });
    }
    
    // Phone field - clear the error when entering the correct phone number
    const phoneInput = form.querySelector('#phone') || form.querySelector('[name="phone"]');
    if (phoneInput) {
      phoneInput.addEventListener('input', () => {
        const phoneValue = phoneInput.value.trim();
        // Checking the phone format (minimum 10 digits)
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        if (phoneValue && phoneRegex.test(phoneValue) && window.clearFieldError) {
          window.clearFieldError(phoneInput);
        }
      });
    }
    
    // Email field - clear the error when entering the correct email
    const emailInput = form.querySelector('#email') || form.querySelector('[name="email"]');
    if (emailInput) {
      emailInput.addEventListener('input', () => {
        const emailValue = emailInput.value.trim();
        if (emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue) && window.clearFieldError) {
          window.clearFieldError(emailInput);
        }
      });
    }
    
    // Guests field - clear the error when selecting a value
    const guestsSelect = form.querySelector('#guests') || form.querySelector('[name="guests"]') || form.querySelector('[name="guests_count"]');
    if (guestsSelect) {
      guestsSelect.addEventListener('change', () => {
        if (guestsSelect.value && parseInt(guestsSelect.value) >= 1 && window.clearFieldError) {
          window.clearFieldError(guestsSelect);
        }
      });
    }
    
    // Pets field - clear the error when selecting a value
    const petsSelect = form.querySelector('#pets') || form.querySelector('[name="pets"]');
    if (petsSelect) {
      petsSelect.addEventListener('change', () => {
        if (petsSelect.value && window.clearFieldError) {
          window.clearFieldError(petsSelect);
        }
      });
    }
}

// Hide the house icon on room pages
function hideOrderIndicatorOnRoomPages() {
  // Checking if we are on the room page
  const isRoomPage = document.querySelector('form.booking-form[data-room]') !== null ||
                     window.location.pathname.includes('room-') ||
                     window.location.pathname.includes('room_') ||
                     document.querySelector('.room-hero') !== null;
  
  if (isRoomPage) {
    // Hide the house icon if it exists (check several times to be sure)
    const hideIndicator = () => {
      const orderIndicator = document.querySelector('.order-indicator');
      if (orderIndicator) {
        orderIndicator.style.display = 'none';
        orderIndicator.style.visibility = 'hidden';
        orderIndicator.style.opacity = '0';
        orderIndicator.remove(); // Removing the element completely
      }
    };
    
    // We hide it right away
    hideIndicator();
    
    // Hide after a short delay (in case the icon is created asynchronously)
    setTimeout(hideIndicator, 100);
    setTimeout(hideIndicator, 500);
    setTimeout(hideIndicator, 1000);
    
    // Disabling the btb:order:record event handler for room pages
    // so that the icon does not appear when booking
    document.addEventListener('btb:order:record', (e) => {
      e.stopImmediatePropagation();
      hideIndicator();
    }, true); // Using capture phase for early interception
    
    // We also intercept events after the DOM is loaded
    document.addEventListener('DOMContentLoaded', hideIndicator);
    
    // Observe DOM changes and hide the icon if it appears
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1) { // Element node
            if (node.classList && node.classList.contains('order-indicator')) {
              hideIndicator();
            }
            // We also check child elements
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

// Room booking form validation (Loki Suite basement form uses data-custom-handler)
// Single function to initialize the booking form
// Used for all room pages
function initBookingForm(form, roomName) {
  if (!form || !roomName) return;
  
  // Hiding the house icon on room pages
  hideOrderIndicatorOnRoomPages();
  
  const flash = (el) => flashDateField(el);
  
  // Setting a constraint for check-in/check-out
  attachCheckinCheckoutConstraint(form, '#checkin', '#checkout');
  
  // Setting up error cleaning after form initialization
  setupFieldErrorClearing(form);
  
  // Initialize blocking of busy dates
  initBlockedDatesForRoom(form, roomName);
  
  // Processing wellness section (only for Basement)
  const wellnessSection = document.getElementById('wellness-section');
  const showWellnessReminder = () => {
    if (wellnessSection) {
      wellnessSection.style.display = 'block';
      wellnessSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  
  // Checking if the wellness section should be shown based on localStorage
  // By default, we show a section if it has not been hidden by the user
  if (wellnessSection) {
    const wellnessHidden = localStorage.getItem('btb_wellness_hidden');
    if (wellnessHidden !== '1') {
      wellnessSection.style.display = 'block';
    } else {
      wellnessSection.style.display = 'none';
    }
  }
  
  // Note that the form has a submit handler
  form.dataset.hasSubmitHandler = 'true';
  
  // Form submit handler
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    e.stopImmediatePropagation();
    
    // Getting form elements
    const checkin = form.querySelector('#checkin');
    const checkout = form.querySelector('#checkout');
    const name = form.querySelector('#name');
    const email = form.querySelector('#email');
    const guests = form.querySelector('#guests');
    const pets = form.querySelector('#pets');
    const phone = form.querySelector('#phone');
    
    // Close Flatpickr calendars if they are open
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
        // Ignore errors when closing calendars
      }
    }

    // Synchronize values ​​from Flatpickr, if used
    if (typeof flatpickr !== 'undefined') {
      try {
        // Getting Flatpickr instances the right way
        const checkinFp = checkin._flatpickr || (checkin.dataset.flatpickrInitialized ? flatpickr(checkin) : null);
        const checkoutFp = checkout._flatpickr || (checkout.dataset.flatpickrInitialized ? flatpickr(checkout) : null);
        
        // Synchronizing check-in - trying different ways to get the value
        let checkinValue = checkin.value;
        if (checkinFp) {
          if (checkinFp.selectedDates && checkinFp.selectedDates.length > 0) {
            checkinValue = checkinFp.formatDate(checkinFp.selectedDates[0], 'Y-m-d');
          } else if (checkinFp.input && checkinFp.input.value) {
            checkinValue = checkinFp.input.value;
          } else if (checkinFp.altInput && checkinFp.altInput.value) {
            // If altInput is used, you need to get the value from the real input
            checkinValue = checkin.value || checkinFp.input.value;
          }
          if (checkinValue && checkinValue !== checkin.value) {
            checkin.value = checkinValue;
            checkin.type = 'date';
            checkin.dispatchEvent(new Event('input', { bubbles: true }));
            checkin.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
        
        // Synchronizing check-out
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
        // Continue with the current inputs values
      }
    }

    // Validating fields in the correct sequence:
    // 1. First check-in (entry)
    if (!checkin.value) {
      if (window.flashDateField) {
        window.flashDateField(checkin);
      } else {
        flash(checkin);
      }
      // For date fields we use .field-error elements (as for regular fields)
      if (window.showDateFieldError) {
        window.showDateFieldError(checkin, 'Please select a check-in date.');
      }
      // Don't call focus() on date fields to avoid scrolling the page
      return;
    }
    // 2. Then check-out (departure)
    if (!checkout.value) {
      if (window.flashDateField) {
        window.flashDateField(checkout);
      } else {
        flash(checkout);
      }
      // For date fields we use .field-error elements (as for regular fields)
      if (window.showDateFieldError) {
        window.showDateFieldError(checkout, 'Please select a check-out date.');
      }
      // Don't call focus() on date fields to avoid scrolling the page
      return;
    }
    // 3. Then name (name)
    if (!name.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(name);
      } else {
        flash(name);
      }
      // Using .field-error elements (as for date fields)
      if (window.showFieldError) {
        window.showFieldError(name, 'Name is required');
      }
      name.focus();
      return;
    }
    // 4. Then phone (telephone)
    if (!phone.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(phone);
      } else {
        flash(phone);
      }
      // Using .field-error elements (as for date fields)
      if (window.showFieldError) {
        window.showFieldError(phone, 'Phone number is required');
      }
      phone.focus();
      return;
    }
    // 5. Then email (mail)
    if (!email.value.trim()) {
      if (window.flashDateField) {
        window.flashDateField(email);
      } else {
        flash(email);
      }
      // Using .field-error elements (as for date fields)
      if (window.showFieldError) {
        window.showFieldError(email, 'Email is required');
      }
      email.focus();
      return;
    }
    // 6. Then guests (number of guests)
    if (!guests.value) {
      if (window.flashDateField) {
        window.flashDateField(guests);
      } else {
        flash(guests);
      }
      // Using .field-error elements (as for date fields)
      if (window.showFieldError) {
        window.showFieldError(guests, 'At least 1 guest is required');
      }
      guests.focus();
      return;
    }
    // 7. Then dogs (0–2)
    if (pets && !pets.value) {
      if (window.flashDateField) {
        window.flashDateField(pets);
      } else {
        flash(pets);
      }
      // Using .field-error elements (as for date fields)
      if (window.showFieldError) {
        window.showFieldError(pets, 'Please select how many dogs');
      }
      pets.focus();
      return;
    }

    // Validation of date order and minimum interval
    const inDate = parseLocalDate(checkin.value);
    const outDate = parseLocalDate(checkout.value);
    if (inDate && outDate) {
      // Check 1: Check out date must be later than check in date
      if (outDate <= inDate) {
        // Clearing setCustomValidity so as not to trigger HTML5 validation
        checkout.setCustomValidity('');
        // Showing an error in the .field-error style (unified system)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkin, DATE_RANGE_MSG);
        }
        // Both fields flash red
        if (window.flashDateField) {
          window.flashDateField(checkin);
          window.flashDateField(checkout);
        } else {
          flash(checkin);
          flash(checkout);
        }
        // Don't call focus() on date fields to avoid scrolling the page
        return;
      }
      // Check 2: Departure date must be at least 2 days later than arrival date
      const checkinPlusOne = new Date(inDate);
      checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
      if (outDate <= checkinPlusOne) {
        // Clearing setCustomValidity so as not to trigger HTML5 validation
        checkout.setCustomValidity('');
        // Showing an error in the .field-error style (unified system)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkout, 'Check-out date must be at least 2 days after check-in date.');
        }
        // Both fields flash red
        if (window.flashDateField) {
          window.flashDateField(checkin);
          window.flashDateField(checkout);
        } else {
          flash(checkin);
          flash(checkout);
        }
        // Don't call focus() on date fields to avoid scrolling the page
        return;
      }
      // If all checks are passed, clear the errors
      checkout.setCustomValidity('');
    }

    // Create a reservation via API
    if (window.BookingAPI && window.BookingAPI.handleBookingForm) {
      // Using the new API
      window.BookingAPI.handleBookingForm(form).then((success) => {
        if (success) {
          // Successful booking creation - redirect to confirmation page
          // (the redirect occurs in handleBookingForm)
        } else {
          // Error creating booking - errors already shown in handleBookingForm
        }
      }).catch((error) => {
        console.error('Booking submission error:', error);
        alert('Failed to create booking. Please try again.');
      });
    } else {
      // Fallback to old behavior if booking.js is not loaded
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
      
      // Show wellness section automatically after successful booking (only for Basement)
      if (roomName === 'Loki Suite') {
        showWellnessReminder();
        // We remove the hide flag so that the section is shown after booking
        localStorage.removeItem('btb_wellness_hidden');
      }
    }
  });
}

// Initializing the form for Basement
const initBasementBooking = () => {
  const form = document.querySelector('form.booking-form[data-room="Loki Suite"]');
  if (form) {
    initBookingForm(form, 'Loki Suite');
  }
};

// Show custom notification with longer display time
// Unified function for showing date errors (uses .field-error system)
// Kept for backwards compatibility, but now uses showFieldError internally
function showDateErrorNotification(input, message, isFirstMessage = false) {
  // We use a unified .field-error system for all fields
  // This ensures consistent error display for all field types
  if (window.showFieldError) {
    window.showFieldError(input, message);
    return;
  }
  
  // Fallback: if showFieldError is not available, use the old system
  // (for backwards compatibility, but this should not happen under normal circumstances)
  console.warn('showFieldError not available, using fallback');
  
  // CRITICAL: Flatpickr with altInput is used for date fields
  // The original input is hidden (1px x 1px), so you need to use the visible altInput for positioning
  let targetInput = input;
  
  // Checking if Flatpickr is used with altInput
  if (typeof flatpickr !== 'undefined' && input._flatpickr) {
    const fpInstance = input._flatpickr;
    // If there is an altInput (visible field), use it for positioning
    if (fpInstance.altInput) {
      targetInput = fpInstance.altInput;
    }
  }
  
  // Use the existing showValidationBubble function if available
  if (window.ValidationUtils && window.ValidationUtils.showBubble) {
    window.ValidationUtils.showBubble(targetInput, message);
    // We show a notification for 4 seconds instead of the standard ones
    setTimeout(() => {
      if (window.hideValidationBubble) {
        window.hideValidationBubble();
      }
    }, 4000);
    return;
  }
  
  // Fallback: using a local implementation in the .btb-bubble style
  // Delete the previous notification, if any.
  const existing = document.querySelector('#btb-bubble');
  if (existing) {
    existing.remove();
  }
  
  // Create a notification in the .btb-bubble style
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
  
  // Position the notification IMMEDIATELY UNDER the visible input
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
  
  // Show notification for 4 seconds
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
  
  // Remove notification on click
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

// Blocking busy dates in date picker
async function initBlockedDatesForRoom(form, roomName) {
  // We immediately mark the inputs so that enhanceDateInputs does not process them
  // This is important because enhanceDateInputs is called synchronously on DOMContentLoaded.
  // and initBlockedDatesForRoom is an asynchronous function
  const checkinInput = form.querySelector('#checkin');
  const checkoutInput = form.querySelector('#checkout');
  
  if (checkinInput && checkoutInput) {
    // We mark inputs BEFORE loading data so that enhanceDateInputs does not process them
    checkinInput.dataset.enhancedDate = '1';
    checkoutInput.dataset.enhancedDate = '1';
  }
  
  try {
    // We receive blocked dates (confirmed bookings + manual blocks + Airbnb)
    const params = new URLSearchParams({
      action: 'get_blocked_dates',
      room_name: roomName
    });
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      console.warn('Failed to load blocked dates for room:', roomName, 'Status:', response.status);
      return;
    }
    
    // Checking that the response is indeed JSON and not HTML (PHP error)
    const contentType = response.headers.get('content-type');
    let result = null;
    
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
      // We continue without blocking dates - the calendar should work
      result = { success: false, data: { blocked_dates: [], airbnb_blocked_dates: [] } };
    } else {
      try {
        result = await response.json();
      } catch (jsonError) {
        console.error('Failed to parse JSON response for blocked dates:', jsonError);
        // We continue without blocking dates - the calendar should work
        result = { success: false, data: { blocked_dates: [], airbnb_blocked_dates: [] } };
      }
    }
    
    let blockedDates = [];
    
    // Getting manual blocking (periods)
    if (result && result.success && result.data?.blocked_dates) {
      // Converting periods into a list of dates for the calendar
      result.data.blocked_dates.forEach(blocked => {
        const dateFrom = blocked.date_from || blocked.blocked_date || '';
        const dateTo = blocked.date_to || blocked.blocked_date || '';
        
        if (dateFrom && dateTo) {
          // Generate all dates in the period using parseLocalDate for correct processing
          const fromDate = parseLocalDate(dateFrom);
          const toDate = parseLocalDate(dateTo);
          
          if (fromDate && toDate) {
            for (let d = new Date(fromDate); d <= toDate; d.setDate(d.getDate() + 1)) {
              const dateStr = formatDateString(d);
              blockedDates.push(dateStr);
            }
          }
        } else if (blocked.blocked_date) {
          // Backward compatibility: if there is only blocked_date
          blockedDates.push(blocked.blocked_date);
        }
      });
    }
    
    // Getting Airbnb blocked dates
    if (result && result.success && result.data?.airbnb_blocked_dates) {
      blockedDates = [...blockedDates, ...result.data.airbnb_blocked_dates];
    }
    
    // We receive confirmed reservations (optional, not critical for the calendar)
    try {
      const bookingsParams = new URLSearchParams({
        action: 'get_bookings',
        room_name: roomName,
        status: 'confirmed'
      });
      
      const bookingsResponse = await fetch('api.php?' + bookingsParams.toString(), {
        method: 'GET'
      });
      
      if (bookingsResponse.ok) {
        const bookingsContentType = bookingsResponse.headers.get('content-type');
        if (bookingsContentType && bookingsContentType.includes('application/json')) {
          try {
            const bookingsResult = await bookingsResponse.json();
            if (bookingsResult.success && bookingsResult.data?.bookings) {
        // Adding all dates from confirmed bookings
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
          } catch (bookingsError) {
            console.warn('Failed to parse bookings response:', bookingsError);
            // We continue without reservations - the calendar should work
          }
        }
      }
    } catch (bookingsFetchError) {
      console.warn('Failed to fetch bookings:', bookingsFetchError);
      // We continue without reservations - the calendar should work
    }
    
    // Removing duplicates
    blockedDates = [...new Set(blockedDates)];
    
    // Blocking dates in date inputs
    const checkinInput = form.querySelector('#checkin');
    const checkoutInput = form.querySelector('#checkout');
    
    if (checkinInput && checkoutInput) {
      // Checking if Flatpickr is available
      const hasFlatpickr = typeof flatpickr !== 'undefined';
      
      if (hasFlatpickr) {
        // Using Flatpickr to visually block dates
        // blockedDates already contains all dates from the periods
        const blockedDatesArray = blockedDates.map(date => {
          const d = parseLocalDate(date);
          return d ? d.toISOString().split('T')[0] : null;
        }).filter(Boolean);
        
        // Disable enhanceDateInputs for these fields (Flatpickr will replace the native picker)
        checkinInput.dataset.enhancedDate = '1';
        checkoutInput.dataset.enhancedDate = '1';
        
        // Remove display proxy inputs from enhanceDateInputs if they exist (to avoid duplication)
        const checkinDisplay = checkinInput.previousElementSibling && checkinInput.previousElementSibling.tagName === 'INPUT' && checkinInput.previousElementSibling.readOnly ? checkinInput.previousElementSibling : null;
        const checkoutDisplay = checkoutInput.previousElementSibling && checkoutInput.previousElementSibling.tagName === 'INPUT' && checkoutInput.previousElementSibling.readOnly ? checkoutInput.previousElementSibling : null;
        
        // Removing display proxy to avoid duplication with Flatpickr
        if (checkinDisplay) {
          checkinDisplay.remove();
        }
        if (checkoutDisplay) {
          checkoutDisplay.remove();
        }
        
        // Restoring the normal state of inputs for Flatpickr
        // Flatpickr styles the inputs itself, so they need to be left visible
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
        
        // Declaring variables for Flatpickr instances in the function scope
        let fpCheckin = null;
        let fpCheckout = null;
        
        // Function for getting dates that need to be blocked for checkout (before checkin + 1 day)
        const getDisabledDatesForCheckout = () => {
          const disabledDates = [...blockedDatesArray];
          
          // If checkin date is selected, we block all dates before checkin + 1 day
          if (checkinInput.value) {
            const checkinDate = parseLocalDate(checkinInput.value);
            if (checkinDate) {
              // We block all dates up to and including checkin
              const today = new Date();
              today.setHours(0, 0, 0, 0);
              let currentDate = new Date(today);
              
              // We block all dates until checkin
              while (currentDate <= checkinDate) {
                const dateStr = formatDateString(currentDate);
                if (!disabledDates.includes(dateStr)) {
                  disabledDates.push(dateStr);
                }
                currentDate.setDate(currentDate.getDate() + 1);
              }
              
              // We block checkin + 1 day
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
        
        // Initializing Flatpickr for check-in
        if (!checkinInput.dataset.flatpickrInitialized) {
          // Making sure the input type remains "date" for HTML5 validation
          checkinInput.type = 'date';
          
          fpCheckin = flatpickr(checkinInput, {
            dateFormat: 'Y-m-d',
            disable: blockedDatesArray,
            minDate: 'today',
            allowInput: false, // Disable direct input to input
            clickOpens: true, // Open the calendar when clicked
            altInput: true, // Using an alternative input to display with placeholder
            altFormat: 'F j, Y', // Date display format (for example: "November 7, 2025")
            placeholder: 'dd.mm.yyyy', // Placeholder for altInput (corresponds to browser format)
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                longhand: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
              },
              months: {
                shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
              }
            },
            onReady: function(selectedDates, dateStr, instance) {
              // Make sure the parent container has position: relative
              const parent = instance.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // We hide the original input because we use altInput for display
              // We move it far to the side so that it does not intercept clicks
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
                instance.input.style.visibility = 'visible'; // Visible to the browser, but invisible to the user
              }
              
              // Making sure altInput has the correct size and clickable area
              if (instance.altInput) {
                instance.altInput.style.width = '100%';
                instance.altInput.style.cursor = 'pointer';
                // Installing placeholder after full initialization
                if (!instance.altInput.value) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                }
              }
            },
            onChange: function(selectedDates, dateStr, instance) {
              // Checking if the selected date is blocked
              if (dateStr && blockedDatesArray.includes(dateStr)) {
                instance.clear();
                checkinInput.value = '';
                // Restoring the placeholder after cleaning
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                // Show the error in the .field-error style (as for regular fields)
                if (window.showDateFieldError) {
                  window.showDateFieldError(checkinInput, 'This date is unavailable. Please select another date.');
                }
                flashDateField(checkinInput);
                return;
              }
              // Make sure that the value of the real input is updated
              if (dateStr) {
                checkinInput.value = dateStr;
                // Make sure the type remains "date"
                checkinInput.type = 'date';
                // Clear the error if the date is selected correctly
                if (window.clearDateFieldError) {
                  window.clearDateFieldError(checkinInput);
                }
                // Calling events to validate the form
                checkinInput.dispatchEvent(new Event('input', { bubbles: true }));
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                
                // Update blocked dates for checkout when checkin changes
                if (fpCheckout) {
                  const updatedDisabledDates = getDisabledDatesForCheckout();
                  fpCheckout.set('disable', updatedDisabledDates);
                  
                  // If checkout is already selected and it has become invalid, we show a notification, but do not clear it
                  // The user will correct the departure date himself
                  if (checkoutInput.value) {
                    const checkoutDate = parseLocalDate(checkoutInput.value);
                    const checkinDate = parseLocalDate(dateStr);
                    if (checkinDate && checkoutDate) {
                      // Check if checkin is selected later than checkout
                      if (checkinDate >= checkoutDate) {
                        // We don’t clear checkout - let the user fix it himself
                        // Show the error in the .field-error style (as for regular fields)
                        if (window.showDateFieldError) {
                          window.showDateFieldError(checkinInput, 'Check-out cannot be earlier than Check-in.\nPlease select a later date.');
                        }
                        // Both fields flash red
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
                          // We don’t clear checkout - let the user fix it himself
                          // Show the error in the .field-error style (as for regular fields)
                          if (window.showDateFieldError) {
                            window.showDateFieldError(checkoutInput, 'Check-out date must be at least 2 days after check-in date.');
                          }
                          // Both fields flash red
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
                // Restore placeholder if the value is empty
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkinInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Update blocked dates for checkout when clearing checkin
                if (fpCheckout) {
                  fpCheckout.set('disable', blockedDatesArray);
                }
              }
            }
          });
          checkinInput.dataset.flatpickrInitialized = '1';
          
          // Hiding the original input immediately after initialization
          setTimeout(() => {
            if (fpCheckin.input && fpCheckin.altInput) {
              // Make sure the parent container has position: relative
              const parent = fpCheckin.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // We move the hidden input far to the side so that it does not intercept clicks
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
              
              // Making sure altInput has the correct size and clickable area
              fpCheckin.altInput.style.width = '100%';
              fpCheckin.altInput.style.cursor = 'pointer';
              
              // Make sure placeholder is set to altInput after initialization
              if (!fpCheckin.altInput.value) {
                fpCheckin.altInput.placeholder = 'dd.mm.yyyy';
              }
            }
          }, 50);
        }
        
        // Initializing Flatpickr for check-out
        if (!checkoutInput.dataset.flatpickrInitialized) {
          // Making sure the input type remains "date" for HTML5 validation
          checkoutInput.type = 'date';
          
          fpCheckout = flatpickr(checkoutInput, {
            dateFormat: 'Y-m-d',
            disable: getDisabledDatesForCheckout(),
            minDate: 'today',
            allowInput: false, // Disable direct input to input
            clickOpens: true, // Open the calendar when clicked
            altInput: true, // Using an alternative input to display with placeholder
            altFormat: 'F j, Y', // Date display format (for example: "November 7, 2025")
            placeholder: 'dd.mm.yyyy', // Placeholder for altInput (corresponds to browser format)
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                longhand: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
              },
              months: {
                shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
              }
            },
            onReady: function(selectedDates, dateStr, instance) {
              // Make sure the parent container has position: relative
              const parent = instance.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // We hide the original input because we use altInput for display
              // We move it far to the side so that it does not intercept clicks
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
                instance.input.style.visibility = 'visible'; // Visible to the browser, but invisible to the user
              }
              
              // Making sure altInput has the correct size and clickable area
              if (instance.altInput) {
                instance.altInput.style.width = '100%';
                instance.altInput.style.cursor = 'pointer';
                // Installing placeholder after full initialization
                if (!instance.altInput.value) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                }
              }
            },
            onOpen: function(selectedDates, dateStr, instance) {
              // Update blocked dates when opening the calendar
              const updatedDisabledDates = getDisabledDatesForCheckout();
              instance.set('disable', updatedDisabledDates);
            },
            onChange: function(selectedDates, dateStr, instance) {
              // We get a list of blocked dates (including dates before checkin + 1)
              const disabledDates = getDisabledDatesForCheckout();
              
              // Checking if the selected date is blocked
              if (dateStr && disabledDates.includes(dateStr)) {
                instance.clear();
                checkoutInput.value = '';
                // Restoring the placeholder after cleaning
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // We determine the reason for blocking and display the corresponding notification
                let errorMessage = 'This date is unavailable. Please select another date.';
                if (checkinInput.value) {
                  const checkinDate = parseLocalDate(checkinInput.value);
                  const selectedDate = parseLocalDate(dateStr);
                  if (checkinDate && selectedDate) {
                    const checkinPlusOne = new Date(checkinDate);
                    checkinPlusOne.setDate(checkinPlusOne.getDate() + 1);
                    if (selectedDate <= checkinPlusOne) {
                      errorMessage = 'Check-out date must be at least 2 days after check-in date.';
                    }
                  }
                }
                
                // Show the error in the .field-error style (as for regular fields)
                if (window.showDateFieldError) {
                  window.showDateFieldError(checkoutInput, errorMessage);
                }
                flashDateField(checkoutInput);
                return;
              }
              // Make sure that the value of the real input is updated
              if (dateStr) {
                checkoutInput.value = dateStr;
                // Make sure the type remains "date"
                checkoutInput.type = 'date';
                // Clear the error if the date is selected correctly
                if (window.clearDateFieldError) {
                  window.clearDateFieldError(checkoutInput);
                }
                // Calling events to validate the form
                checkoutInput.dispatchEvent(new Event('input', { bubbles: true }));
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
              } else {
                checkoutInput.value = '';
                // Restore placeholder if the value is empty
                if (instance.altInput) {
                  instance.altInput.placeholder = 'dd.mm.yyyy';
                  instance.altInput.value = '';
                }
                checkoutInput.dispatchEvent(new Event('change', { bubbles: true }));
              }
            }
          });
          checkoutInput.dataset.flatpickrInitialized = '1';
          
          // Hiding the original input immediately after initialization
          setTimeout(() => {
            if (fpCheckout.input && fpCheckout.altInput) {
              // Make sure the parent container has position: relative
              const parent = fpCheckout.input.parentElement;
              if (parent && window.getComputedStyle(parent).position === 'static') {
                parent.style.position = 'relative';
              }
              
              // We move the hidden input far to the side so that it does not intercept clicks
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
              
              // Making sure altInput has the correct size and clickable area
              fpCheckout.altInput.style.width = '100%';
              fpCheckout.altInput.style.cursor = 'pointer';
            }
          }, 50);
        }
      } else {
        // Fallback: using standard validation with improved notification
        const validateDateSelection = (input, dateValue) => {
          if (!dateValue) return true;
          const dateStr = dateValue;
          if (blockedDates.includes(dateStr)) {
            // Clearing setCustomValidity so as not to trigger HTML5 validation
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
    // We do not interrupt execution - we continue to initialize the form without blocking dates
    // This will allow the calendar to work even if the API is not available
  }
}

// Formatting date in YYYY-MM-DD
function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Initializing forms for other rooms
const initOtherRoomWellness = () => {
  const forms = document.querySelectorAll('form.booking-form[data-custom-handler]:not([data-room="Loki Suite"])');
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
    
    // Initialize Flatpickr for date input in massage form (same approach as room pages)
    const dateInput = massageForm.querySelector('input[type="date"]#date');
    if (dateInput && typeof flatpickr !== 'undefined' && !dateInput.dataset.flatpickrInitialized) {
      // Mark as enhanced BEFORE Flatpickr initialization to prevent enhanceDateInputs from processing it
      dateInput.dataset.enhancedDate = '1';
      // Making sure the input type remains "date" for HTML5 validation
      dateInput.type = 'date';
      
      const fpDate = flatpickr(dateInput, {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        allowInput: false, // Disable direct input to input
        clickOpens: true, // Open the calendar when clicked
        altInput: true, // Using an alternative input to display with placeholder
        altFormat: 'F j, Y', // Date display format (for example: "November 7, 2025")
        placeholder: 'dd.mm.yyyy', // Placeholder for altInput (corresponds to browser format)
        onReady: function(selectedDates, dateStr, instance) {
          // Make sure the parent container has position: relative
          const parent = instance.input.parentElement;
          if (parent && window.getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
          }
          
          // We hide the original input because we use altInput for display
          // We move it far to the side so that it does not intercept clicks
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
            instance.input.style.visibility = 'visible'; // Visible to the browser, but invisible to the user
          }
          
          // Making sure altInput has the correct size and clickable area
          if (instance.altInput) {
            instance.altInput.style.width = '100%';
            instance.altInput.style.cursor = 'pointer';
            // Installing placeholder after full initialization
            if (!instance.altInput.value) {
              instance.altInput.placeholder = 'dd.mm.yyyy';
            }
          }
        },
        onChange: function(selectedDates, dateStr, instance) {
          // Make sure that the value of the real input is updated
          if (dateStr) {
            dateInput.value = dateStr;
            // Make sure the type remains "date"
            dateInput.type = 'date';
            // Calling events to validate the form
            dateInput.dispatchEvent(new Event('input', { bubbles: true }));
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
          } else {
            dateInput.value = '';
            // Restore placeholder if the value is empty
            if (instance.altInput) {
              instance.altInput.placeholder = 'dd.mm.yyyy';
              instance.altInput.value = '';
            }
            dateInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      });
      
      dateInput.dataset.flatpickrInitialized = '1';
      
      // Hiding the original input immediately after initialization
      setTimeout(() => {
        if (fpDate.input && fpDate.altInput) {
          // Make sure the parent container has position: relative
          const parent = fpDate.input.parentElement;
          if (parent && window.getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
          }
          
          // We move the hidden input far to the side so that it does not intercept clicks
          fpDate.input.style.position = 'absolute';
          fpDate.input.style.opacity = '0';
          fpDate.input.style.width = '0';
          fpDate.input.style.height = '0';
          fpDate.input.style.padding = '0';
          fpDate.input.style.margin = '0';
          fpDate.input.style.border = 'none';
          fpDate.input.style.pointerEvents = 'none';
          fpDate.input.style.left = '-9999px';
          fpDate.input.style.top = '-9999px';
          fpDate.input.style.visibility = 'visible';
          
          // Making sure altInput has the correct size and clickable area
          fpDate.altInput.style.width = '100%';
          fpDate.altInput.style.cursor = 'pointer';
          
          // Make sure placeholder is set to altInput after initialization
          if (!fpDate.altInput.value) {
            fpDate.altInput.placeholder = 'dd.mm.yyyy';
          }
        }
      }, 50);
      
      // Loading blocked dates for massage
      initBlockedDatesForMassage(massageForm, fpDate);
    }
  }
}

// Blocking busy dates in date picker for massage
async function initBlockedDatesForMassage(form, fpInstance) {
  if (!form || !fpInstance) return;
  
  try {
    // Getting blocked dates for massage (manual blocking for "Massage" and "__all__")
    const params = new URLSearchParams({
      action: 'get_blocked_dates',
      room_name: 'Massage'
    });
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      console.warn('Failed to load blocked dates for massage:', 'Status:', response.status);
      return;
    }
    
    // Checking that the response is indeed JSON and not HTML (PHP error)
    const contentType = response.headers.get('content-type');
    let result = null;
    
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('API returned non-JSON response for blocked dates:', text.substring(0, 200));
      // We continue without blocking dates - the calendar should work
      result = { success: false, data: { blocked_dates: [] } };
    } else {
      try {
        result = await response.json();
      } catch (jsonError) {
        console.error('Failed to parse JSON response for blocked dates:', jsonError);
        // We continue without blocking dates - the calendar should work
        result = { success: false, data: { blocked_dates: [] } };
      }
    }
    
    let blockedDates = [];
    
    // Getting manual blocking (periods)
    if (result && result.success && result.data?.blocked_dates) {
      // Converting periods into a list of dates for the calendar
      result.data.blocked_dates.forEach(blocked => {
        // We take into account blocking for "Massage" and "__all__" (for everyone)
        const isRelevant = blocked.room_name === 'Massage' || blocked.room_name === '__all__';
        
        if (isRelevant) {
          const dateFrom = blocked.date_from || blocked.blocked_date || '';
          const dateTo = blocked.date_to || blocked.blocked_date || '';
          
          if (dateFrom && dateTo) {
            // Generate all dates in the period using parseLocalDate for correct processing
            const fromDate = parseLocalDate(dateFrom);
            const toDate = parseLocalDate(dateTo);
            
            if (fromDate && toDate) {
              for (let d = new Date(fromDate); d <= toDate; d.setDate(d.getDate() + 1)) {
                const dateStr = formatDateString(d);
                blockedDates.push(dateStr);
              }
            }
          } else if (blocked.blocked_date) {
            // Backward compatibility: if there is only blocked_date
            blockedDates.push(blocked.blocked_date);
          }
        }
      });
    }
    
    // Removing duplicates
    blockedDates = [...new Set(blockedDates)];
    
    // Blocking dates in Flatpicr
    if (blockedDates.length > 0) {
      // Convert dates to Flatpickr format (YYYY-MM-DD)
      const blockedDatesArray = blockedDates.map(date => {
        const d = parseLocalDate(date);
        return d ? d.toISOString().split('T')[0] : null;
      }).filter(Boolean);
      
      // Update the disable option in Flatpickr
      if (fpInstance && fpInstance.config) {
        // Getting the current blocked dates
        const currentDisabled = fpInstance.config.disable || [];
        
        // Combine with new blocked dates
        const allDisabled = [...new Set([...currentDisabled, ...blockedDatesArray])];
        
        // Updating the Flatpickr configuration
        fpInstance.set('disable', allDisabled);
        
        console.log(`Blocked dates initialized for Massage: ${blockedDates.length} dates`);
      }
    } else {
      console.log('Blocked dates initialized for Massage: 0 dates');
    }
  } catch (error) {
    console.error('Failed to initialize blocked dates for massage:', error);
    // We continue without blocking dates - the calendar should work
  }
}

function formatMassageDurationLabelForToast(type, minutesStr) {
  const m = String(minutesStr);
  if (type === 'Sauna' && m === '60') return '1 hour';
  if (m === '15') return '15 min';
  if (m === '30') return '30 min';
  if (m === '60') return '60 min';
  if (m === '90') return '90 min';
  return `${m} min`;
}

/**
 * Non-blocking hint after adding a massage/sauna line to the cart (no auto-scroll).
 * Includes a control to jump to the booking block (#book).
 */
function showMassageBookingToast(message) {
  const text = String(message || '').trim();
  if (!text) return;
  let el = document.getElementById('btb-massage-booking-toast');
  if (el && !el.querySelector('.btb-massage-booking-toast__msg')) {
    el.remove();
    el = null;
  }
  if (!el) {
    el = document.createElement('div');
    el.id = 'btb-massage-booking-toast';
    el.className = 'btb-massage-booking-toast-inner';
    const msg = document.createElement('p');
    msg.className = 'btb-massage-booking-toast__msg';
    msg.id = 'btb-massage-booking-toast-msg';
    msg.setAttribute('role', 'status');
    msg.setAttribute('aria-live', 'polite');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn outline btb-massage-booking-toast__action';
    btn.textContent = 'Go to booking';
    btn.setAttribute('aria-describedby', 'btb-massage-booking-toast-msg');
    el.appendChild(msg);
    el.appendChild(btn);
    document.body.appendChild(el);
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const book = document.getElementById('book');
      if (book) {
        book.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const massageFormEl = document.getElementById('massage-form');
        const focusTarget = massageFormEl && massageFormEl.querySelector('#date');
        if (focusTarget && typeof focusTarget.focus === 'function') {
          setTimeout(() => {
            try {
              focusTarget.focus({ preventScroll: true });
            } catch (_) {
              focusTarget.focus();
            }
          }, 400);
        }
      }
      el.classList.remove('btb-massage-booking-toast--visible');
      if (el._hideToastTimer) {
        clearTimeout(el._hideToastTimer);
        el._hideToastTimer = null;
      }
    });
  }
  const msgEl = el.querySelector('.btb-massage-booking-toast__msg');
  if (msgEl) {
    msgEl.textContent = text;
  }
  el.classList.add('btb-massage-booking-toast--visible');
  if (el._hideToastTimer) {
    clearTimeout(el._hideToastTimer);
  }
  el._hideToastTimer = setTimeout(() => {
    el.classList.remove('btb-massage-booking-toast--visible');
  }, 9500);
}

// Initialize clickable massage options
function initClickableMassageOptions() {
  const massageOptions = document.querySelectorAll('.massage-list li');
  const form = document.querySelector('#massage-form');
  
  if (!form) return;
  
  const typeSelect = form.querySelector('#type');
  const durationSelect = form.querySelector('#duration');
  
  massageOptions.forEach((option) => {
    option.style.cursor = 'pointer';
    const activate = () => {
      const card = option.closest('.card-massage');
      const massageType = (card && card.getAttribute('data-massage-card-type')
        ? String(card.getAttribute('data-massage-card-type')).trim()
        : '');
      let duration = option.getAttribute('data-m-duration')
        ? String(option.getAttribute('data-m-duration')).trim()
        : '';
      if (!duration) {
        const durationText = option.textContent;
        const minutesMatch = durationText.match(/(\d+)\s*minutes?/i);
        const hourMatch = durationText.match(/(\d+)\s*hours?/i);
        if (minutesMatch) {
          duration = minutesMatch[1];
        } else if (hourMatch) {
          duration = String(parseInt(hourMatch[1], 10) * 60);
        }
      }

      if (!massageType || !duration) {
        return;
      }

      if (window.BookingAPI && typeof window.BookingAPI.addMassageCartLine === 'function') {
        const added = window.BookingAPI.addMassageCartLine(massageType, duration);
        if (added) {
          if (typeof window.BookingAPI.renderMassageCartUI === 'function') {
            window.BookingAPI.renderMassageCartUI(form);
          }
          const durLabel = formatMassageDurationLabelForToast(massageType, duration);
          showMassageBookingToast(
            `Added: ${massageType} (${durLabel}). Choose date and time in the booking section below.`
          );
        }
      } else if (typeSelect && durationSelect) {
        if (typeSelect) {
          typeSelect.value = massageType;
          typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (duration) {
          setTimeout(() => {
            durationSelect.value = duration;
          }, 0);
        }
        option.style.background = 'var(--brand)';
        option.style.color = 'white';
        setTimeout(() => {
          option.style.background = '';
          option.style.color = '';
        }, 1000);
        const durLabel = formatMassageDurationLabelForToast(massageType, duration);
        showMassageBookingToast(
          `${massageType} (${durLabel}) is selected below. Add date and time when you are ready.`
        );
      }
    };

    option.addEventListener('click', activate);
    option.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activate();
      }
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
  const dateInputs = Array.from(root.querySelectorAll('input[type="date"]')).filter(i => !i.dataset.enhancedDate && !i._flatpickr);
  dateInputs.forEach(real => {
    real.dataset.enhancedDate = '1';
    const display = document.createElement('input');
    display.type = 'text';
    display.value = formatIso(real.value);
    display.placeholder = 'dd.mm.yyyy';
    display.readOnly = true;
    
    // Make sure the parent container has position: relative for proper positioning
    const parent = real.parentElement;
    if (parent && window.getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }
    
    // Insert display before real; keep real hidden but present for JS/submit
    // We hide the real input completely so that it does not intercept clicks
    real.style.position = 'absolute';
    real.style.opacity = '0';
    real.style.pointerEvents = 'none';
    real.style.width = '0';
    real.style.height = '0';
    real.style.margin = '0';
    real.style.padding = '0';
    real.style.border = 'none';
    real.style.left = '-9999px'; // Move it far to the side so as not to interfere
    real.style.top = '-9999px';
    
    // Making sure the visual display input has the correct size and clickable area
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
          // Clearing setCustomValidity so as not to trigger HTML5 validation
          checkout.setCustomValidity('');
          // For date fields we use .field-error elements (unified system)
          if (window.showDateFieldError) {
            window.showDateFieldError(checkout, DATE_RANGE_MSG);
          }
          flashDateField(checkout);
          // Don't call focus() on date fields to avoid scrolling the page
        }
      }
    };
    const validateCheckout = () => {
      if (!checkin.value || !checkout.value) return;
      const dIn = parseLocalDate(checkin.value);
      const dOut = parseLocalDate(checkout.value);
      if (dOut <= dIn) {
        // Clearing setCustomValidity so as not to trigger HTML5 validation
        checkout.setCustomValidity('');
        // For date fields we use .field-error elements (unified system)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkout, DATE_RANGE_MSG);
        }
        flashDateField(checkout);
        // Don't call focus() on date fields to avoid scrolling the page
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
        // Clearing setCustomValidity so as not to trigger HTML5 validation
        checkout.setCustomValidity('');
        // For date fields we use .field-error elements (unified system)
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
    // We use the unified .field-error system instead of .btb-bubble
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
      // Don't call focus() on date fields to avoid scrolling the page
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
    // Skip images that are loaded via SSR or marked as no-resolve
    if (img.hasAttribute('data-ssr-loaded') || img.hasAttribute('data-no-resolve')) {
      return;
    }
    
    const baseAttr = img.getAttribute('data-src-base');
    if (!baseAttr) return;
    
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
  // Globally disable HTML5 validation for all forms on the site
  // This prevents browser gray prompts from appearing
  document.querySelectorAll('form').forEach(form => {
    form.setAttribute('novalidate', 'novalidate');
    // We do not touch forms with an explicit handler and the password change modal (dashboard.js - in the bubble phase;
    // otherwise stopImmediatePropagation here will kill the entire submit and the button “does nothing”).
    const skipCaptureBlock =
      form.dataset.hasSubmitHandler ||
      form.id === 'change-password-form' ||
      form.id === 'message-form' ||
      (form.closest && form.closest('#password-modal'));
    if (skipCaptureBlock) {
      return;
    }
    form.addEventListener('submit', (e) => {
      if (!form.dataset.hasSubmitHandler) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    }, true);
  });
  
  // Additionally: disable HTML5 validation for all inputs with the required attribute
  // This prevents gray prompts from appearing when attempting to submit
  document.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
    // We remove the required attribute, but save it in data-required for our validation
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
  // initOrderIndicator(); // Disabled - the house icon is no longer shown
  
  // Hide the house icon on room pages if it has been created
  hideOrderIndicatorOnRoomPages();
  
  // Initialize Flatpickr for massage form BEFORE enhanceDateInputs to prevent conflicts
  initMassageTimeRestrictions();
  
  initDateTimeUX();
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
  try {
    localStorage.setItem('btb_orders', JSON.stringify(orders));
    try {
      document.dispatchEvent(new CustomEvent('btb:orders:changed'));
    } catch (_) {}
  } catch (_) {}
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
// By default, the section is shown on all room pages
// Hides only if the user has explicitly closed it
function checkAndShowWellnessSection() {
  try {
    const section = document.getElementById('wellness-section');
    if (section) {
      // Checking whether the section has been hidden by the user
      const wellnessHidden = localStorage.getItem('btb_wellness_hidden');
      if (wellnessHidden !== '1') {
        // Show the section by default if it has not been hidden
        section.style.display = 'block';
      } else {
        // Hide a section if the user has closed it
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
      // Set the flag that the section has been hidden by the user
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
    if (currentDataTheme === 'dark' || currentDataTheme === 'light' || currentDataTheme === 'twilight') {
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
    
    // Also handle mobile theme toggle
    const mobileThemeToggle = document.getElementById('mobile-theme-toggle');
    if (mobileThemeToggle) {
      mobileThemeToggle.addEventListener('click', () => {
        this.toggleTheme();
      });
    }
  }

  toggleTheme() {
    // Cycle through themes: dark -> twilight -> light -> dark
    if (this.currentTheme === 'dark') {
      this.currentTheme = 'twilight';
    } else if (this.currentTheme === 'twilight') {
      this.currentTheme = 'light';
    } else {
      this.currentTheme = 'dark';
    }
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
    const mobileThemeText = document.getElementById('mobile-theme-text');
    const themeIcons = document.querySelectorAll('.theme-toggle-icon path');
    
    // Update desktop theme text
    if (themeText) {
      // Show the next theme name in cycle: dark -> twilight -> light -> dark
      if (theme === 'dark') {
        themeText.textContent = 'Twilight';
      } else if (theme === 'twilight') {
        themeText.textContent = 'Light';
      } else {
        themeText.textContent = 'Dark';
      }
    }
    
    // Update mobile theme text
    if (mobileThemeText) {
      if (theme === 'dark') {
        mobileThemeText.textContent = 'Twilight';
      } else if (theme === 'twilight') {
        mobileThemeText.textContent = 'Light';
      } else {
        mobileThemeText.textContent = 'Dark';
      }
    }
    
    // Update all theme icons (desktop and mobile)
    themeIcons.forEach(themeIcon => {
      // Update icon based on what theme we'll switch TO, not current theme
      // dark -> twilight -> light -> dark
      if (theme === 'dark') {
        // Show icon for twilight (next theme)
        themeIcon.setAttribute('d', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z');
      } else if (theme === 'twilight') {
        // Sun icon for switching to light
        themeIcon.setAttribute('d', 'M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
      } else {
        // Moon icon for switching to dark
        themeIcon.setAttribute('d', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z');
      }
    });
  }
}

// Initialize theme manager
let themeManager;

// Initialize when DOM is ready
// Load contact information from API
async function loadContactInfo() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData,
      cache: 'no-store'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data) {
        const data = result.data;
        
        // Update footer contact information
        const addressEl = document.getElementById('footer-contact-address');
        const phoneEl = document.getElementById('footer-contact-phone');
        const emailEl = document.getElementById('footer-contact-email');
        
        if (addressEl) {
          addressEl.textContent = data.contactAddress || 'British Columbia, Canada';
        }
        if (phoneEl) {
          phoneEl.textContent = 'Phone: ' + (data.contactPhone || '+1 (555) 123‑4567');
        }
        if (emailEl) {
          emailEl.textContent = 'Email: ' + (data.contactEmail || 'hello@backtobase.example');
        }

        // About page — section heading (Contact us) + text next to the form (phone/email/address: footer)
        const aboutContactHeading = document.getElementById('about-contact-section-heading');
        if (aboutContactHeading) {
          const ht = (data.aboutContactFormTitle && String(data.aboutContactFormTitle).trim()) || '';
          if (ht) aboutContactHeading.textContent = ht;
        }
        const aboutContactDesc = document.getElementById('about-contact-description');
        if (aboutContactDesc) {
          let desc = (data.aboutContactFormDescription && String(data.aboutContactFormDescription).trim()) || '';
          if (!desc) {
            const l = (data.aboutContactFormLead && String(data.aboutContactFormLead).trim()) || '';
            const e = (data.aboutContactFormEmphasis && String(data.aboutContactFormEmphasis).trim()) || '';
            desc = [l, e].filter(Boolean).join('\n\n');
          }
          if (desc) {
            aboutContactDesc.textContent = desc;
            aboutContactDesc.style.whiteSpace = 'pre-line';
          }
        }

        // Wellness / massage page — booking block: only replace title/intro when API sends non-empty text
        // (if empty, keep server-rendered HTML; avoids stomping good PHP output on fetch quirks).
        const mBookTitle = document.getElementById('massage-booking-title');
        if (mBookTitle) {
          const t = (data.massageBookingTitle && String(data.massageBookingTitle).trim()) || '';
          if (t) mBookTitle.textContent = t;
        }
        const mBookIntro = document.querySelector('.massage-booking-intro');
        if (mBookIntro) {
          const t = (data.massageBookingIntro && String(data.massageBookingIntro).trim()) || '';
          if (t) mBookIntro.textContent = t;
        }
      }
    }
  } catch (error) {
    console.log('Failed to load contact info:', error);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Initialize theme manager
  themeManager = new ThemeManager();
  
  // Set year in footer
  setYear();
  
  // Load contact information
  loadContactInfo();
  
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
  
  // Load homepage data from admin
  loadHomepageFromAdmin();
});

// Load floorplan data from admin and update content
async function loadFloorplanFromAdmin() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_floorplan');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData,
      cache: 'no-store'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      // Only update if we have actual data from database
      if (result.success && result.data) {
        // Always apply API payload (including cleared fields). Old gate skipped updates when all subtitles were empty.
        updateFloorPlanContent(result.data);
      }
    }
  } catch (error) {
    // Silently fail - site should show default content
    console.log('Floorplan API not available, using default content');
  }
}

// Load homepage data from admin and update hero images
async function loadHomepageFromAdmin() {
  try {
    const formData = new FormData();
    formData.append('action', 'get_content');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData,
      cache: 'no-store'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      // Only update if we have actual data from database
      if (result.success && result.data) {
        updateHomepageContent(result.data);
      }
    }
  } catch (error) {
    // Silently fail - site should show default content
    console.log('Homepage API not available, using default content');
  }
}

// Update homepage hero images on the page
// NOTE: Hero images, descriptions, and Wellness Experiences are now loaded via Server-Side Rendering (SSR) in index.php
// This function is kept for backward compatibility but only updates room cards images
function updateHomepageContent(data) {
  try {
    console.log('Updating homepage content with data:', data);
    
    // Hero images, descriptions, and Wellness Experiences are loaded via SSR
    // Only update room cards images from localStorage for backward compatibility
    
    // Update room cards images
    const roomBasementCardImageUrl = data.roomBasementCardImageUrl || data.room_basement_card_image_url || '';
    const roomGroundQueenCardImageUrl = data.roomGroundQueenCardImageUrl || data.room_ground_queen_card_image_url || '';
    const roomGroundTwinCardImageUrl = data.roomGroundTwinCardImageUrl || data.room_ground_twin_card_image_url || '';
    const roomSecondCardImageUrl = data.roomSecondCardImageUrl || data.room_second_card_image_url || '';
    
    // Update room cards background images
    // Skip if images are loaded via SSR (have data-ssr-loaded attribute)
    const roomCards = document.querySelectorAll('.room-card');
    if (roomCards.length >= 1 && roomBasementCardImageUrl) {
      const basementCard = roomCards[0];
      const roomMedia = basementCard.querySelector('.room-media');
      if (roomMedia && !roomMedia.hasAttribute('data-ssr-loaded')) {
        // Only update if not loaded via SSR
        roomMedia.style.backgroundImage = `url('${roomBasementCardImageUrl}?v=${Date.now()}')`;
        console.log('Updated basement room card image:', roomBasementCardImageUrl);
      } else if (roomMedia && roomMedia.hasAttribute('data-ssr-loaded')) {
        console.log('Skipping basement room card image update - loaded via SSR');
      }
    }
    
    if (roomCards.length >= 2 && roomGroundQueenCardImageUrl) {
      const groundQueenCard = roomCards[1];
      const roomMedia = groundQueenCard.querySelector('.room-media');
      if (roomMedia && !roomMedia.hasAttribute('data-ssr-loaded')) {
        // Only update if not loaded via SSR
        roomMedia.style.backgroundImage = `url('${roomGroundQueenCardImageUrl}?v=${Date.now()}')`;
        console.log('Updated ground queen room card image:', roomGroundQueenCardImageUrl);
      } else if (roomMedia && roomMedia.hasAttribute('data-ssr-loaded')) {
        console.log('Skipping ground queen room card image update - loaded via SSR');
      }
    }
    
    if (roomCards.length >= 3 && roomGroundTwinCardImageUrl) {
      const groundTwinCard = roomCards[2];
      const roomMedia = groundTwinCard.querySelector('.room-media');
      if (roomMedia && !roomMedia.hasAttribute('data-ssr-loaded')) {
        // Only update if not loaded via SSR
        roomMedia.style.backgroundImage = `url('${roomGroundTwinCardImageUrl}?v=${Date.now()}')`;
        console.log('Updated ground twin room card image:', roomGroundTwinCardImageUrl);
      } else if (roomMedia && roomMedia.hasAttribute('data-ssr-loaded')) {
        console.log('Skipping ground twin room card image update - loaded via SSR');
      }
    }
    
    if (roomCards.length >= 4 && roomSecondCardImageUrl) {
      const secondCard = roomCards[3];
      const roomMedia = secondCard.querySelector('.room-media');
      if (roomMedia && !roomMedia.hasAttribute('data-ssr-loaded')) {
        // Only update if not loaded via SSR
        roomMedia.style.backgroundImage = `url('${roomSecondCardImageUrl}?v=${Date.now()}')`;
        console.log('Updated second floor room card image:', roomSecondCardImageUrl);
      } else if (roomMedia && roomMedia.hasAttribute('data-ssr-loaded')) {
        console.log('Skipping second floor room card image update - loaded via SSR');
      }
    }
    
    // Merge into localStorage so we never wipe unrelated keys (admin used to store other fields under btb_content).
    let prev = {};
    try {
      prev = JSON.parse(localStorage.getItem('btb_content') || '{}');
    } catch (e) {
      prev = {};
    }
    const contentData = {
      ...prev,
      roomBasementCardImageUrl: roomBasementCardImageUrl,
      roomGroundQueenCardImageUrl: roomGroundQueenCardImageUrl,
      roomGroundTwinCardImageUrl: roomGroundTwinCardImageUrl,
      roomSecondCardImageUrl: roomSecondCardImageUrl
    };
    localStorage.setItem('btb_content', JSON.stringify(contentData));
    console.log('Homepage room card image URLs merged into localStorage');
    
  } catch (error) {
    console.error('Error updating homepage content:', error);
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

/**
 * Match PHP safeOutputWithBreaks(): escape HTML; newlines → <br>; "- item" runs → ul.desc-bullets.
 * Used when Common areas (floorplan) text is applied from API after SSR (textContent would collapse \n).
 */
function btbEscapeHtmlForCmsText(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function btbFormatCmsPlainMultiline(value, fallback) {
  const fb = fallback != null ? String(fallback) : '';
  let text = value != null ? String(value) : fb;
  text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  const lines = text.split('\n');
  const runs = [];
  let bufText = [];
  let bufList = [];
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const m = /^\s*-\s*(.*)$/u.exec(line);
    if (m) {
      if (bufText.length) {
        runs.push({ type: 't', lines: bufText });
        bufText = [];
      }
      bufList.push(m[1]);
    } else {
      if (bufList.length) {
        runs.push({ type: 'l', items: bufList });
        bufList = [];
      }
      bufText.push(line);
    }
  }
  if (bufText.length) runs.push({ type: 't', lines: bufText });
  if (bufList.length) runs.push({ type: 'l', items: bufList });
  let html = '';
  for (const run of runs) {
    if (run.type === 't') {
      const joined = run.lines.join('\n');
      html += btbEscapeHtmlForCmsText(joined).replace(/\n/g, '<br>');
    } else {
      html += '<ul class="desc-bullets">';
      for (const item of run.items) {
        html += '<li>' + btbEscapeHtmlForCmsText(item) + '</li>';
      }
      html += '</ul>';
    }
  }
  return html;
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
      
      if (basementSubtitle) {
        basementSubtitle.innerHTML = btbFormatCmsPlainMultiline(basementTitle, '');
        console.log('Updated basement subtitle');
      }
      if (basementDesc) {
        basementDesc.innerHTML = btbFormatCmsPlainMultiline(basementDescription, '');
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
      
      if (groundSubtitle) {
        groundSubtitle.innerHTML = btbFormatCmsPlainMultiline(groundTitle, '');
      }
      if (groundDesc) {
        groundDesc.innerHTML = btbFormatCmsPlainMultiline(groundDescription, '');
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
      
      if (loftSubtitle) {
        loftSubtitle.innerHTML = btbFormatCmsPlainMultiline(loftTitle, '');
      }
      if (loftDesc) {
        loftDesc.innerHTML = btbFormatCmsPlainMultiline(loftDescription, '');
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

// Load floor plan data — prefer API so the public site matches the database (localStorage was stale after CMS saves).
function loadFloorPlanData() {
  try {
    console.log('Loading floor plan data from API...');
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
      body: 'action=get_floorplan',
      cache: 'no-store'
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
// NOTE: Floor Plan content (text and images) is now loaded via Server-Side Rendering (SSR) in index.php
// This function is kept for backward compatibility but is disabled for SSR pages
function initFloorPlanData() {
  // Only load floor plan data on main site, not in admin
  // Skip if page is SSR (has data-ssr-loaded attributes on floor plan images)
  if (!window.location.pathname.includes('admin')) {
    const floorPlanImages = document.querySelectorAll('.floor-photo[data-ssr-loaded]');
    if (floorPlanImages.length > 0) {
      // Page uses SSR, skip JavaScript loading
      console.log('Floor Plan content loaded via SSR, skipping JavaScript update');
      return;
    }
    // Fallback for non-SSR pages
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

// Mobile Navigation Menu
function initMobileMenu() {
  const menuToggle = document.getElementById('mobile-menu-toggle');
  const mobileNav = document.getElementById('mobile-nav');
  const overlay = document.getElementById('mobile-nav-overlay');
  
  if (!menuToggle || !mobileNav || !overlay) {
    return; // Mobile menu elements not found on this page
  }
  
  function openMenu() {
    menuToggle.classList.add('active');
    mobileNav.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent body scroll
  }
  
  function closeMenu() {
    menuToggle.classList.remove('active');
    mobileNav.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = ''; // Restore body scroll
  }
  
  // Toggle menu on button click
  menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    if (mobileNav.classList.contains('active')) {
      closeMenu();
    } else {
      openMenu();
    }
  });
  
  // Close menu when clicking overlay
  overlay.addEventListener('click', closeMenu);
  
  // Close menu when clicking a link (but not theme toggle)
  const mobileNavLinks = mobileNav.querySelectorAll('a');
  mobileNavLinks.forEach(link => {
    link.addEventListener('click', () => {
      closeMenu();
    });
  });
  
  // Update mobile nav sign in button if user is authenticated
  const mobileNavSignin = mobileNav.querySelector('#mobile-nav-signin');
  if (mobileNavSignin && window.authSystem) {
    // Check auth status and update button text if needed
    const updateMobileSignin = () => {
      try {
        if (window.authSystem && typeof window.authSystem.isAuthenticated === 'function') {
          const isAuth = window.authSystem.isAuthenticated();
          if (isAuth && window.authSystem.user) {
            mobileNavSignin.textContent = window.authSystem.user.name || 'Account';
            mobileNavSignin.href = 'dashboard.html';
          } else {
            mobileNavSignin.textContent = 'Guest login';
            mobileNavSignin.href = 'login.html';
          }
        }
      } catch (e) {
        // Ignore errors
      }
    };
    
    // Update on load
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', updateMobileSignin);
    } else {
      updateMobileSignin();
    }
    
    // Listen for auth changes
    window.addEventListener('authStateChanged', updateMobileSignin);
  }
  
  // Don't close menu when clicking theme toggle button
  const mobileThemeToggle = mobileNav.querySelector('#mobile-theme-toggle');
  if (mobileThemeToggle) {
    mobileThemeToggle.addEventListener('click', (e) => {
      e.stopPropagation(); // Prevent menu from closing
    });
  }
  
  // Close menu on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNav.classList.contains('active')) {
      closeMenu();
    }
  });
  
  // Close menu on window resize if screen becomes larger than mobile
  window.addEventListener('resize', () => {
    if (window.innerWidth > 767 && mobileNav.classList.contains('active')) {
      closeMenu();
    }
  });
}

// Initialize mobile menu when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMobileMenu);
} else {
  initMobileMenu();
}

// Pending booking hint (shared module; one place to load for all pages using script.js)
(function btbLoadPendingBookingHint() {
  function inject() {
    if (document.getElementById('btb-pending-booking-hint-loader')) return;
    var s = document.createElement('script');
    s.id = 'btb-pending-booking-hint-loader';
    s.src = 'pending-booking-hint.js';
    s.async = true;
    document.head.appendChild(s);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
