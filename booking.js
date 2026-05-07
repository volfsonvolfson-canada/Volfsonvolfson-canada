/**
 * Booking.js
 * Processing of booking forms and integration with API
 */

// API configuration
const BOOKING_API_URL = 'api.php';

/** Room booking dog count 0–2 (bookings.pets). Legacy: add / yes / true → 1, no → 0. */
function btbNormalizeRoomDogCountFromFormValue(raw) {
  if (raw === true) {
    return 1;
  }
  const s = String(raw ?? '').trim().toLowerCase();
  if (s === 'add' || s === 'yes' || s === 'true') {
    return 1;
  }
  if (s === 'no' || s === '') {
    return 0;
  }
  const n = parseInt(s, 10);
  if (!Number.isFinite(n)) {
    return 0;
  }
  return Math.min(2, Math.max(0, n));
}

/** Fallback copy for post-booking overlay (rooms + massage) if API fails */
const DEFAULT_BOOKING_SUCCESS_BANNER = {
  heading: 'Your booking has been submitted!',
  paragraph:
    "We've sent you a confirmation email. Once your booking is approved, you'll be able to proceed with the payment.\n\nYou can also make changes to your booking in your personal account.",
  button_label: 'My Account',
  button_url: 'dashboard.html',
  auth_login_message:
    'Welcome back!\n\nAll your bookings are available in your personal account.\n\nYou can find it in the menu in the top right corner of the site',
  auth_login_close_label: 'Close',
  auth_login_account_label: 'To my account',
  auth_login_account_url: 'dashboard.html',
};

function btbMergeLegacyBannerParagraphs(p1, p2) {
  const a = String(p1 || '').trim();
  const b = String(p2 || '').trim();
  if (!b) {
    return String(p1 || '');
  }
  if (!a) {
    return b;
  }
  return `${a}\n\n${b}`;
}

function btbSafeBannerButtonHref(url) {
  const h = String(url || '').trim();
  if (!h) {
    return DEFAULT_BOOKING_SUCCESS_BANNER.button_url;
  }
  const lower = h.toLowerCase();
  if (lower.startsWith('javascript:') || lower.startsWith('data:')) {
    return DEFAULT_BOOKING_SUCCESS_BANNER.button_url;
  }
  return h;
}

async function fetchBookingSuccessBannerCopy() {
  try {
    const res = await fetch(`${BOOKING_API_URL}?action=get_booking_success_banner`, { cache: 'no-store' });
    const json = await res.json();
    if (json && json.success && json.data && typeof json.data === 'object') {
      const d = json.data;
      const heading =
        d.heading != null && String(d.heading).trim() !== ''
          ? String(d.heading)
          : DEFAULT_BOOKING_SUCCESS_BANNER.heading;
      let paragraph = d.paragraph != null ? String(d.paragraph) : '';
      if (paragraph.trim() === '' && (d.paragraph_1 != null || d.paragraph_2 != null)) {
        paragraph = btbMergeLegacyBannerParagraphs(d.paragraph_1, d.paragraph_2);
      }
      if (paragraph.trim() === '') {
        paragraph = DEFAULT_BOOKING_SUCCESS_BANNER.paragraph;
      }
      const buttonLabel =
        d.button_label != null && String(d.button_label).trim() !== ''
          ? String(d.button_label)
          : DEFAULT_BOOKING_SUCCESS_BANNER.button_label;
      const buttonUrl = btbSafeBannerButtonHref(d.button_url);
      let authLoginMessage =
        d.auth_login_message != null && String(d.auth_login_message).trim() !== ''
          ? String(d.auth_login_message)
          : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_message;
      const authClose =
        d.auth_login_close_label != null && String(d.auth_login_close_label).trim() !== ''
          ? String(d.auth_login_close_label)
          : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_close_label;
      const authAcctLabel =
        d.auth_login_account_label != null && String(d.auth_login_account_label).trim() !== ''
          ? String(d.auth_login_account_label)
          : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_account_label;
      const authAcctUrl = btbSafeBannerButtonHref(
        d.auth_login_account_url != null && String(d.auth_login_account_url).trim() !== ''
          ? String(d.auth_login_account_url)
          : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_account_url,
      );
      return {
        heading,
        paragraph,
        button_label: buttonLabel,
        button_url: buttonUrl,
        auth_login_message: authLoginMessage,
        auth_login_close_label: authClose,
        auth_login_account_label: authAcctLabel,
        auth_login_account_url: authAcctUrl,
      };
    }
  } catch (e) {
    console.warn('fetchBookingSuccessBannerCopy:', e);
  }
  return { ...DEFAULT_BOOKING_SUCCESS_BANNER };
}

/**
 * “Basket” of services on the Massage page: several items (type + duration) with quantity.
 * The key is type + NUL + duration. After successful sending it is cleared.
 */
const massageBookingCart = Object.create(null);

function massageCartLineKey(type, duration) {
  return `${String(type || '').trim()}\u0000${String(duration || '').trim()}`;
}

/** Collect allowed durations for a booking type from rendered price lines on the page. */
function massageAllowedDurationsFromDom(type) {
  const t = String(type || '').trim();
  const set = new Set();
  if (!t || typeof document === 'undefined' || !document.querySelectorAll) {
    return set;
  }
  document.querySelectorAll('.massage-list li[data-m-type][data-m-duration]').forEach((li) => {
    if ((li.getAttribute('data-m-type') || '').trim() === t) {
      set.add(String(li.getAttribute('data-m-duration') || '').trim());
    }
  });
  return set;
}

/** @returns {string|null} error message or null if OK */
function validateMassageServiceCombo(type, durationStr) {
  const t = String(type || '').trim();
  const d = String(durationStr || '').trim();
  if (!t) return 'Service type is required';
  if (!d) return 'Duration is required';
  const fromDom = massageAllowedDurationsFromDom(t);
  if (fromDom.size > 0) {
    return fromDom.has(d) ? null : 'Choose a duration from the list for this service';
  }
  if (t === 'Sauna') {
    return d === '60' ? null : 'Sauna is booked as 1 hour (60 minutes)';
  }
  if (t === 'Reiki Energy Healing') {
    return d === '15' || d === '30' ? null : 'Reiki is available for 15 or 30 minutes';
  }
  if (t === 'Relaxing Massage' || t === 'Deep Tissue Massage') {
    return d === '60' || d === '90' ? null : 'Choose 60 or 90 minutes for this massage';
  }
  return 'Unknown service type';
}

function getMassageCartLines() {
  return Object.entries(massageBookingCart)
    .map(([key, qty]) => {
      const parts = key.split('\u0000');
      const type = parts[0] || '';
      const duration = parts[1] || '';
      return { key, type, duration, qty: Math.max(0, Number(qty) || 0) };
    })
    .filter((line) => line.qty > 0)
    .sort((a, b) => `${a.type} ${a.duration}`.localeCompare(`${b.type} ${b.duration}`));
}

function getMassageCartTotalCount() {
  return getMassageCartLines().reduce((sum, line) => sum + line.qty, 0);
}

function addMassageCartLine(type, duration) {
  const err = validateMassageServiceCombo(type, duration);
  if (err) {
    console.warn('addMassageCartLine:', err, type, duration);
    return false;
  }
  const key = massageCartLineKey(type, duration);
  massageBookingCart[key] = (massageBookingCart[key] || 0) + 1;
  return true;
}

function removeMassageCartLineCompletely(type, duration) {
  const key = massageCartLineKey(type, duration);
  delete massageBookingCart[key];
}

const MASSAGE_CART_SS_KEY = 'btb_massage_cart_draft';

function persistMassageCartDraft() {
  try {
    const lines = getMassageCartLines();
    if (lines.length === 0) {
      sessionStorage.removeItem(MASSAGE_CART_SS_KEY);
    } else {
      sessionStorage.setItem(MASSAGE_CART_SS_KEY, JSON.stringify(lines));
    }
  } catch (_) {}
}

function clearMassageBookingCart() {
  Object.keys(massageBookingCart).forEach((k) => {
    delete massageBookingCart[k];
  });
  try {
    sessionStorage.removeItem(MASSAGE_CART_SS_KEY);
  } catch (_) {}
}

function formatMassageDurationLabel(type, minutesStr) {
  const m = String(minutesStr);
  if (type === 'Sauna' && m === '60') return '1 hour';
  if (m === '15') return '15 min';
  if (m === '30') return '30 min';
  if (m === '60') return '60 min';
  if (m === '90') return '90 min';
  return `${m} min`;
}

function attachMassageCartFormDelegates(form) {
  if (!form || form.id !== 'massage-form') return;
  // Cart panel (#massage-cart-panel) is a sibling of the form, not inside it — delegate from their common parent.
  const root = form.parentElement;
  if (!root || root._btbMassageCartClickDelegated) return;
  root._btbMassageCartClickDelegated = true;
  root.addEventListener('click', (e) => {
    const rem = e.target.closest('.massage-cart-remove');
    if (!rem) return;
    e.preventDefault();
    const type = (rem.getAttribute('data-m-type') || '').trim();
    const duration = (rem.getAttribute('data-m-duration') || '').trim();
    removeMassageCartLineCompletely(type, duration);
    const f = document.getElementById('massage-form');
    if (f) renderMassageCartUI(f);
  });
}

function renderMassageCartUI(form) {
  if (!form || form.id !== 'massage-form') return;
  attachMassageCartFormDelegates(form);
  const lines = getMassageCartLines();
  const panelEl = document.getElementById('massage-cart-panel');
  const ulEl = document.getElementById('massage-cart-lines');
  const submitBtn = document.getElementById('massage-submit-btn');
  const hasLines = lines.length > 0;

  if (ulEl) {
    ulEl.innerHTML = '';
    lines.forEach((line) => {
      const li = document.createElement('li');
      li.className = 'massage-cart-line';
      const label = document.createElement('span');
      label.className = 'massage-cart-line-label';
      label.textContent = `${line.type} — ${formatMassageDurationLabel(line.type, line.duration)} × ${line.qty}`;
      const actions = document.createElement('span');
      actions.className = 'massage-cart-line-actions';
      const remBtn = document.createElement('button');
      remBtn.type = 'button';
      remBtn.className = 'btn outline massage-cart-remove';
      remBtn.textContent = 'Remove';
      remBtn.setAttribute('data-m-type', line.type);
      remBtn.setAttribute('data-m-duration', line.duration);
      remBtn.setAttribute(
        'aria-label',
        `Remove ${line.type} ${formatMassageDurationLabel(line.type, line.duration)} from selection`
      );
      actions.appendChild(remBtn);
      li.appendChild(label);
      li.appendChild(actions);
      ulEl.appendChild(li);
    });
  }

  if (panelEl) {
    panelEl.hidden = !hasLines;
  }

  if (submitBtn) {
    const total = getMassageCartTotalCount();
    const defaultSvc =
      (submitBtn.getAttribute('data-btb-default-service-label') || '').trim() || 'Book service';
    const cartTpl = (submitBtn.getAttribute('data-btb-cart-submit-label') || '').trim();
    if (total > 0) {
      if (cartTpl !== '') {
        submitBtn.textContent = cartTpl.includes('{n}')
          ? cartTpl.split('{n}').join(String(total))
          : cartTpl;
      } else {
        submitBtn.textContent = `Book ${total} service request${total === 1 ? '' : 's'}`;
      }
    } else {
      submitBtn.textContent = defaultSvc;
    }
  }

  syncMassagePriceLinePressedState();

  persistMassageCartDraft();

  const typeEl = form.querySelector('#type');
  const durationEl = form.querySelector('#duration');
  if (
    typeEl &&
    durationEl &&
    typeEl.tagName === 'SELECT' &&
    durationEl.tagName === 'SELECT'
  ) {
    if (hasLines) {
      typeEl.disabled = true;
      durationEl.disabled = true;
    } else {
      typeEl.disabled = false;
      if (typeof form._setMassageDurations === 'function') {
        form._setMassageDurations();
      } else {
        durationEl.disabled = false;
      }
    }
  }
}

const MASSAGE_LI_IN_CART_CLASS = 'massage-li-in-cart';

function syncMassagePriceLinePressedState() {
  const lines = getMassageCartLines();
  const active = new Set(
    lines.filter((l) => l.qty > 0).map((l) => massageCartLineKey(l.type, l.duration))
  );
  document.querySelectorAll('.massage-list li[data-m-type][data-m-duration]').forEach((li) => {
    const t = li.getAttribute('data-m-type');
    const d = li.getAttribute('data-m-duration');
    const on = active.has(massageCartLineKey(t, d));
    li.classList.toggle(MASSAGE_LI_IN_CART_CLASS, on);
    li.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
}

async function postSingleMassageBookingRequest(formData) {
  const formDataToSend = new FormData();
  formDataToSend.append('action', 'create_massage_booking');
  formDataToSend.append('massage_type', formData.type);
  formDataToSend.append('duration', formData.duration);
  formDataToSend.append('massage_date', formData.date);
  formDataToSend.append('massage_time', formData.time);
  formDataToSend.append('guest_name', formData.name);
  formDataToSend.append('email', formData.email);
  formDataToSend.append('phone', formData.phone);
  if (formData.withRoom) {
    formDataToSend.append('with_room', formData.withRoom);
  }

  const response = await fetch(BOOKING_API_URL, {
    method: 'POST',
    body: formDataToSend
  });

  if (!response.ok) {
    const errorText = await response.text();
    console.error('MassageAPI: HTTP error:', response.status, errorText);
    throw new Error(`Failed to create massage booking: HTTP ${response.status}`);
  }

  const result = await response.json();
  if (!result.success) {
    throw new Error(result.error || 'Failed to create massage booking');
  }
  return result;
}

/**
 * Save successful massage/sauna booking to localStorage (btb_orders) and emit btb:order:record — same idea as room bookings.
 * @param {object} apiJson Parsed API response { success, data: { booking_id, confirmation_code } }
 * @param {{ type?: string, duration?: string, date?: string, time?: string, name?: string, email?: string, phone?: string }} payload
 */
function appendMassageOrderToLocalStorage(apiJson, payload) {
  const data = apiJson && apiJson.data ? apiJson.data : {};
  const bookingId = data.booking_id;
  if (!bookingId) {
    console.warn('appendMassageOrderToLocalStorage: missing booking_id in API response');
    return;
  }
  const confirmationCode = data.confirmation_code || '';
  const ta = data.total_amount;
  const totalAmount = ta != null && ta !== '' ? Number(ta) : NaN;
  const order = {
    id: `massage_${bookingId}`,
    kind: 'massage',
    type: String(payload.type || ''),
    duration: String(payload.duration || ''),
    date: payload.date || '',
    time: payload.time || '',
    name: payload.name || '',
    email: payload.email || '',
    phone: payload.phone || '',
    booking_id: bookingId,
    confirmation_code: confirmationCode,
    status: 'pending',
    ts: Date.now()
  };
  if (Number.isFinite(totalAmount) && totalAmount >= 0) {
    order.total_amount = totalAmount;
  }
  if (data.currency) {
    order.currency = String(data.currency);
  }
  try {
    const raw = localStorage.getItem('btb_orders') || '[]';
    const orders = JSON.parse(raw);
    if (!Array.isArray(orders)) return;
    orders.push(order);
    localStorage.setItem('btb_orders', JSON.stringify(orders));
    document.dispatchEvent(new CustomEvent('btb:order:record', { detail: order }));
  } catch (e) {
    console.error('appendMassageOrderToLocalStorage:', e);
  }
}

// parseLocalDate - parses YYYY-MM-DD date as a local date (without time zone)
// Used to avoid time zone issues
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
 * Checking date availability for a room
 * @param {string} roomName Room name
 * @param {string} checkinDate Check-in date (YYYY-MM-DD)
 * @param {string} checkoutDate Check-out date (YYYY-MM-DD)
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
 * Making a reservation
 * @param {Object} bookingData Booking data
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
    formData.append('pets', String(btbNormalizeRoomDogCountFromFormValue(bookingData.pets)));
    if (bookingData.special_requests) {
      formData.append('special_requests', bookingData.special_requests);
    }

    console.log('BookingAPI.createBooking: Sending request to:', BOOKING_API_URL);

    // Showing the loading indicator
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
      nightly_rate: result.data?.nightly_rate,
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
 * Receiving a reservation by ID or confirmation code
 * @param {number|string} bookingIdOrCode Booking ID or confirmation code
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
 * Validation of booking form data
 * @param {Object} formData Form data
 * @returns {Object} {valid: boolean, errors: Object}
 */
function validateBookingForm(formData) {
  const errors = {};

  // Validation in the correct sequence:
  // 1. First check-in (entry)
  if (!formData.checkin_date || !formData.checkin_date.trim()) {
    errors.checkin_date = 'Check-in date is required';
  }

  // 2. Then check-out (departure)
  if (!formData.checkout_date || !formData.checkout_date.trim()) {
    errors.checkout_date = 'Check-out date is required';
  }

  // 3. Checking the order of dates (only if both dates are filled in)
  if (formData.checkin_date && formData.checkout_date) {
    // FIXED: Using parseLocalDate to correctly handle dates without a time zone
    // parseLocalDate parses YYYY-MM-DD as a local date (without time)
    const checkin = parseLocalDate(formData.checkin_date);
    const checkout = parseLocalDate(formData.checkout_date);
    
    if (!checkin || !checkout) {
      if (!checkin) errors.checkin_date = 'Invalid check-in date format';
      if (!checkout) errors.checkout_date = 'Invalid check-out date format';
    } else {
      // We compare only dates (without time)
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

  // 4. Then name
  if (!formData.guest_name || !formData.guest_name.trim()) {
    errors.guest_name = 'Name is required';
  }

  // 5. Then phone (telephone)
  if (!formData.phone || !formData.phone.trim()) {
    errors.phone = 'Phone number is required';
  } else {
    // Checking the phone format (minimum 10 digits)
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(formData.phone.trim())) {
      errors.phone = 'Invalid phone number';
    }
  }

  // 6. Then email (mail)
  if (!formData.email || !formData.email.trim()) {
    errors.email = 'Email is required';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Invalid email address';
  }

  // 7. Then guests (number of guests)
  if (!formData.guests_count || formData.guests_count < 1) {
    errors.guests_count = 'At least 1 guest is required';
  }

  // 8. Dogs (0–2)
  const dogN =
    typeof formData.pets === 'number'
      ? formData.pets
      : btbNormalizeRoomDogCountFromFormValue(formData.pets);
  if (dogN < 0 || dogN > 2) {
    errors.pets = 'Please select how many dogs';
  }

  // Checking the room (not critical for consistency, but we leave it)
  if (!formData.room_name || !formData.room_name.trim()) {
    errors.room_name = 'Room selection is required';
  }

  return {
    valid: Object.keys(errors).length === 0,
    errors
  };
}

/**
 * Processing the booking form
 * @param {HTMLFormElement} form Form element
 * @returns {Promise<boolean>} Processing success
 */
async function handleBookingForm(form) {
  try {
    console.log('BookingAPI.handleBookingForm: Starting...');
    console.log('BookingAPI.handleBookingForm: Form element:', form);
    
    // Collecting form data
    // Getting the room name from a form attribute or hidden field
    let roomName = form.querySelector('[name="room_name"]')?.value || '';
    if (!roomName && form.getAttribute('data-room')) {
      roomName = form.getAttribute('data-room');
    }
    console.log('BookingAPI.handleBookingForm: Room name:', roomName);
    
    // Getting dates - checking different possible selectors
    // First we try to get the values ​​directly from inputs
    let checkinEl = form.querySelector('#checkin') || form.querySelector('[name="checkin"]') || form.querySelector('[name="checkin_date"]');
    let checkoutEl = form.querySelector('#checkout') || form.querySelector('[name="checkout"]') || form.querySelector('[name="checkout_date"]');
    
    // If Flatpicr is used, synchronize the values ​​before reading
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
      pets: btbNormalizeRoomDogCountFromFormValue(
        (form.querySelector('#pets') || form.querySelector('[name="pets"]'))?.value
      ),
      special_requests: form.querySelector('[name="special_requests"]')?.value || ''
    };
    
    console.log('BookingAPI.handleBookingForm: Collected form data:', formData);

    // HTML5 validation - for regular fields we use .field-error elements
    // (we don't use reportValidity() since it shows .btb-bubble and not .field-error)
    // Validation will be done via validateBookingForm below

    // Validation
    console.log('BookingAPI: Validating form data...', formData);
    const validation = validateBookingForm(formData);
    console.log('BookingAPI: Validation result:', validation);
    if (!validation.valid) {
      console.error('BookingAPI: Validation failed:', validation.errors);
      
      // Find the first field with the error in the correct sequence:
      // 1. check-in, 2. check-out, 3. name, 4. others
      const checkinEl = form.querySelector('#checkin') || form.querySelector('[name="checkin"]') || form.querySelector('[name="checkin_date"]');
      const checkoutEl = form.querySelector('#checkout') || form.querySelector('[name="checkout"]') || form.querySelector('[name="checkout_date"]');
      const nameEl = form.querySelector('#name') || form.querySelector('[name="name"]') || form.querySelector('[name="guest_name"]');
      
      // 1. First we check the check-in
      if (validation.errors.checkin_date && checkinEl) {
        // For date fields we use .field-error elements (as for regular fields)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkinEl, validation.errors.checkin_date);
        }
        if (window.flashDateField) {
          window.flashDateField(checkinEl);
        }
        // Don't call focus() on date fields to avoid scrolling the page
        return false;
      }
      
      // 2. Then check-out
      if (validation.errors.checkout_date && checkoutEl) {
        // For date fields we use .field-error elements (as for regular fields)
        if (window.showDateFieldError) {
          window.showDateFieldError(checkoutEl, validation.errors.checkout_date);
        }
        if (window.flashDateField) {
          if (checkinEl) window.flashDateField(checkinEl);
          window.flashDateField(checkoutEl);
        }
        // Don't call focus() on date fields to avoid scrolling the page
        return false;
      }
      
      // 3. Then name (name)
      if (validation.errors.guest_name && nameEl) {
        // Using .field-error elements (as for date fields)
        if (window.showFieldError) {
          window.showFieldError(nameEl, validation.errors.guest_name);
        }
        if (window.flashDateField) {
          window.flashDateField(nameEl);
        }
        nameEl.focus();
        return false;
      }
      
      // 4. Then phone (telephone)
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
      
      // 5. Then email (mail)
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
      
      // 6. Then guests (number of guests)
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
      
      // 7. Then pets (presence of pets)
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
      
      // 8. Then the rest of the fields - show the first error
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

    // Clearing previous errors
    clearFormErrors(form);

    // Checking availability
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

    // Create a reservation
    console.log('BookingAPI: Creating booking with data:', formData);
    const result = await createBooking(formData);
    console.log('BookingAPI: Booking creation result:', result);

    if (!result.success) {
      console.error('BookingAPI: Booking creation failed:', result.error);
      showFormError(form, 'submit', result.error || 'Failed to create booking');
      return false;
    }

    // Successful creation of a reservation
    // Saving booking data in sessionStorage for the confirmation page
    if (result.booking_id) {
      sessionStorage.setItem('last_booking_id', result.booking_id.toString());
      sessionStorage.setItem('last_confirmation_code', result.confirmation_code || '');
      
      // If payment is required, we save payment details
      if (result.payment_required && result.client_secret) {
        sessionStorage.setItem('payment_intent_id', result.payment_intent_id || '');
        sessionStorage.setItem('client_secret', result.client_secret);
      }
      
      // We save the reservation in localStorage to display the house icon and edit it
      try {
        const orders = JSON.parse(localStorage.getItem('btb_orders') || '[]');
        const nr = result.nightly_rate;
        const nightlyNum = typeof nr === 'number' ? nr : parseFloat(String(nr ?? ''), 10);
        const bookingOrder = {
          id: `booking_${result.booking_id}`,
          kind: 'room',
          room: formData.room_name,
          checkin: formData.checkin_date,
          checkout: formData.checkout_date,
          guests: formData.guests_count,
          pets: String(btbNormalizeRoomDogCountFromFormValue(formData.pets)),
          name: formData.guest_name,
          email: formData.email,
          phone: formData.phone,
          booking_id: result.booking_id,
          confirmation_code: result.confirmation_code,
          nightly_rate: Number.isFinite(nightlyNum) && nightlyNum > 0 ? nightlyNum : undefined,
          status: 'pending',
          ts: Date.now()
        };
        orders.push(bookingOrder);
        localStorage.setItem('btb_orders', JSON.stringify(orders));
        
        // Create an event to update the house icon
        document.dispatchEvent(new CustomEvent('btb:order:record', { detail: bookingOrder }));
        
        console.log('BookingAPI: Booking saved to localStorage for editing');
      } catch (error) {
        console.error('BookingAPI: Failed to save booking to localStorage:', error);
        // We do not interrupt the process if it was not possible to save to localStorage
      }
    }

    // We display a message about successful booking on the same page
    console.log('BookingAPI: Booking created successfully');
    console.log('BookingAPI: Booking ID:', result.booking_id);
    console.log('BookingAPI: Confirmation code:', result.confirmation_code);
    
    // Checking the authorization status
    const isAuthenticated = window.authSystem && window.authSystem.isAuthenticated;
    
    // Show a message about successful booking
    await showBookingSuccessMessage(form, {
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
 * Show form errors
 * @param {HTMLFormElement} form Form element
 * @param {Object} errors Object with errors
 */
function showFormErrors(form, errors) {
  // Clearing previous errors
  clearFormErrors(form);

  // Showing errors for each field
  Object.keys(errors).forEach(fieldName => {
    const field = form.querySelector(`[name="${fieldName}"]`) || 
                  form.querySelector(`#${fieldName}`) ||
                  (fieldName === 'checkin_date' ? form.querySelector('#checkin') || form.querySelector('[name="checkin"]') : null) ||
                  (fieldName === 'checkout_date' ? form.querySelector('#checkout') || form.querySelector('[name="checkout"]') : null);
    
    if (field) {
      field.classList.add('invalid-field');
      
      // For all fields (including dates) we use the same .field-error elements
      // which are inserted after the field
      const errorMsg = document.createElement('div');
      errorMsg.className = 'field-error';
      errorMsg.textContent = errors[fieldName];
      errorMsg.id = `error-${fieldName}`;
      field.parentNode?.insertBefore(errorMsg, field.nextSibling);
    }
  });
}

/**
 * Show error for a specific field
 * @param {HTMLFormElement} form Form element
 * @param {string} fieldName Field name
 * @param {string} message Error message
 */
function showFormError(form, fieldName, message) {
  // Determining which field is associated with the error
  let field = null;
  
  // Special cases for errors from the server
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
    // Trying to find a field by name
    field = form.querySelector(`[name="${fieldName}"]`) || form.querySelector(`#${fieldName}`);
  }
  
  // If the field is found, we show an error next to it
  if (field && window.showFieldError) {
    window.showFieldError(field, message);
    if (window.flashDateField) {
      window.flashDateField(field);
    }
    field.focus();
  } else {
    // If the field is not found, show an error in the container (fallback)
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
 * Clear form errors
 * @param {HTMLFormElement} form Form element
 */
function clearFormErrors(form) {
  // Removing error classes from fields
  form.querySelectorAll('.invalid-field').forEach(field => {
    field.classList.remove('invalid-field');
  });

  // Removing error messages
  form.querySelectorAll('.field-error').forEach(error => {
    error.remove();
  });

  // Clearing the general error container
  const errorContainer = form.querySelector('.form-errors');
  if (errorContainer) {
    errorContainer.innerHTML = '';
  }
}

/**
 * Show download status
 */
function showLoadingState() {
  // Create a download overlay if it doesn’t exist yet
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

    // Adding CSS animation for the spinner
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
 * Hide loading status
 */
function hideLoadingState() {
  const overlay = document.getElementById('booking-loading-overlay');
  if (overlay) {
    overlay.style.display = 'none';
  }
}

function closeBookingSuccessOverlay() {
  document.getElementById('btb-booking-success-overlay')?.remove();
  try {
    document.body.style.overflow = '';
    const y = document.body.dataset.btbBookingSuccessScroll;
    if (y != null && y !== '') {
      window.scrollTo(0, parseInt(y, 10) || 0);
    }
    delete document.body.dataset.btbBookingSuccessScroll;
  } catch (_) {}
}

/**
 * Green nested box after sign-in / register inside the post-booking overlay.
 * @param {string} messageText - Body (use newlines for paragraphs). Ignored if useDbLoginMessage.
 * @param {{ useDbLoginMessage?: boolean }} [options]
 */
async function showAuthSuccessMessage(messageText, options = {}) {
  const { useDbLoginMessage = false } = options;
  const messageContainer = document.querySelector('.booking-success-message');
  if (!messageContainer) {
    return;
  }

  const copy = await fetchBookingSuccessBannerCopy();
  let text = String(messageText || '');
  if (useDbLoginMessage) {
    text =
      copy.auth_login_message && String(copy.auth_login_message).trim() !== ''
        ? String(copy.auth_login_message)
        : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_message;
  }
  const closeLabel =
    copy.auth_login_close_label && String(copy.auth_login_close_label).trim() !== ''
      ? String(copy.auth_login_close_label)
      : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_close_label;
  const acctLabel =
    copy.auth_login_account_label && String(copy.auth_login_account_label).trim() !== ''
      ? String(copy.auth_login_account_label)
      : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_account_label;
  const acctHref = btbSafeBannerButtonHref(
    copy.auth_login_account_url && String(copy.auth_login_account_url).trim() !== ''
      ? String(copy.auth_login_account_url)
      : DEFAULT_BOOKING_SUCCESS_BANNER.auth_login_account_url,
  );

  let authMessageContainer = messageContainer.querySelector('.auth-success-message');
  if (!authMessageContainer) {
    authMessageContainer = document.createElement('div');
    authMessageContainer.className = 'auth-success-message';
    authMessageContainer.style.cssText = `
      margin-top: 24px;
      padding: 20px;
      background: rgba(61, 220, 151, 0.1);
      border: 1px solid rgba(61, 220, 151, 0.3);
      border-radius: 12px;
      text-align: left;
    `;
    messageContainer.appendChild(authMessageContainer);
  }

  authMessageContainer.innerHTML = '';

  const bodyWrap = document.createElement('div');
  String(text).split('\n').forEach((line) => {
    if (line.trim() === '') {
      bodyWrap.appendChild(document.createElement('br'));
      return;
    }
    const p = document.createElement('p');
    p.style.cssText = 'margin: 0 0 8px; color: var(--text); font-size: 16px; line-height: 1.6;';
    p.textContent = line;
    bodyWrap.appendChild(p);
  });
  authMessageContainer.appendChild(bodyWrap);

  const btnRow = document.createElement('div');
  btnRow.style.cssText =
    'display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; align-items: center;';

  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'btn outline';
  closeBtn.textContent = closeLabel;
  closeBtn.addEventListener('click', () => closeBookingSuccessOverlay());

  const acctA = document.createElement('a');
  acctA.href = acctHref;
  acctA.className = 'btn primary';
  acctA.textContent = acctLabel;

  btnRow.appendChild(closeBtn);
  btnRow.appendChild(acctA);
  authMessageContainer.appendChild(btnRow);

  authMessageContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Show booking success message
 * @param {HTMLFormElement} form - Booking form
 * @param {Object} options - Options
 * @param {boolean} options.isAuthenticated - Whether the user is authorized
 * @param {Object} options.bookingData - Booking data {name, email, phone}
 */
async function showBookingSuccessMessage(form, options = {}) {
  const { isAuthenticated = false, bookingData = {} } = options;
  const copy = await fetchBookingSuccessBannerCopy();

  document.getElementById('btb-booking-success-overlay')?.remove();
  try {
    document.body.dataset.btbBookingSuccessScroll = String(window.scrollY || 0);
    document.body.style.overflow = 'hidden';
  } catch (_) {}

  const overlay = document.createElement('div');
  overlay.id = 'btb-booking-success-overlay';
  overlay.className = 'btb-booking-success-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'btb-booking-success-title');

  const messageContainer = document.createElement('div');
  messageContainer.className = 'booking-success-message btb-booking-success-dialog';

  const messageText = document.createElement('div');
  messageText.className = 'booking-success-body';
  messageText.style.marginBottom = '20px';
  const titleHeading = document.createElement('h2');
  titleHeading.id = 'btb-booking-success-title';
  titleHeading.className = 'booking-success-heading';
  titleHeading.textContent = copy.heading || DEFAULT_BOOKING_SUCCESS_BANNER.heading;
  messageText.appendChild(titleHeading);
  const bodyText = String(copy.paragraph != null ? copy.paragraph : '').trim()
    ? String(copy.paragraph)
    : DEFAULT_BOOKING_SUCCESS_BANNER.paragraph;
  if (String(bodyText).trim()) {
    const bodyEl = document.createElement('p');
    bodyEl.textContent = bodyText;
    bodyEl.style.whiteSpace = 'pre-line';
    bodyEl.style.margin = '0 0 8px';
    messageText.appendChild(bodyEl);
  }
  messageContainer.appendChild(messageText);

  if (form && form.id === 'massage-form') {
    const cart = document.getElementById('massage-cart-panel');
    if (cart) {
      cart.hidden = true;
    }
  }

  if (!isAuthenticated) {
    const authMenuContainer = document.createElement('div');
    authMenuContainer.id = 'booking-success-auth-menu';
    authMenuContainer.style.cssText = 'margin-top: 24px;';
    messageContainer.appendChild(authMenuContainer);

    overlay.appendChild(messageContainer);
    document.body.appendChild(overlay);

    if (form) {
      form.style.display = 'none';
    }
    
    // Initialize the authorization menu with pre-filled data
    if (window.createAuthMenu) {
      setTimeout(async () => {
        // Checking if a user with this email exists
        let defaultTab = 'register';
        if (window.authSystem && bookingData.email) {
          try {
            const existingUser = await window.authSystem.findUserByEmail(bookingData.email);
            if (existingUser) {
              defaultTab = 'signin';
            }
          } catch (error) {
            console.error('Error checking existing user:', error);
          }
        }
        
        window.createAuthMenu('#booking-success-auth-menu', {
          defaultTab: defaultTab,
          prefillData: {
            name: bookingData.name || '',
            email: bookingData.email || '',
            phone: bookingData.phone || ''
          },
          onReady: () => {
            // Apply styles to set the width of the form to the width of the message
            const authContainer = document.querySelector('#booking-success-auth-menu .auth-container');
            if (authContainer) {
              // The shape should match the width of the message above
              authContainer.style.maxWidth = '100%';
              authContainer.style.width = '100%';
              authContainer.style.minWidth = '0';
              
              // Inputs remain perfectly sized (use the entire available width of the container)
              const inputs = authContainer.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="password"]');
              inputs.forEach(input => {
                input.style.width = '100%';
                input.style.maxWidth = 'none';
                input.style.minWidth = '0';
              });
              
              // Field containers use all available width
              const formRows = authContainer.querySelectorAll('.form-row > div');
              formRows.forEach(div => {
                div.style.width = '100%';
                div.style.maxWidth = 'none';
                div.style.minWidth = '0';
              });
            }
          },
          onLogin: async (user) => {
            // After logging in, show a message and update the interface
            await showAuthSuccessMessage('', { useDbLoginMessage: true });
            
            // Updating the buttons in the header
            if (window.authSystem) {
              window.authSystem.updateHeaderButtons();
            }
            
            // Hide the authorization form and show the message
            const authMenuContainer = document.getElementById('booking-success-auth-menu');
            if (authMenuContainer) {
              authMenuContainer.style.display = 'none';
            }
          },
          onRegister: async (user) => {
            // After registration, we automatically log in the user
            if (window.authSystem && user) {
              await window.authSystem.loginUser(user);
              
              // Showing the message
              await showAuthSuccessMessage(
                'Congratulations! You have created an account on our website. \n\nNow all your bookings are available in your personal account. \n\nYou can find it in the menu in the top right corner of the site',
                { useDbLoginMessage: false },
              );
              
              // Updating the buttons in the header
              window.authSystem.updateHeaderButtons();
              
              // Hide the authorization form and show the message
              const authMenuContainer = document.getElementById('booking-success-auth-menu');
              if (authMenuContainer) {
                authMenuContainer.style.display = 'none';
              }
            }
          }
        });
      }, 100);
    }
  } else {
    const accountButton = document.createElement('a');
    accountButton.href = btbSafeBannerButtonHref(copy.button_url);
    accountButton.className = 'btn primary';
    accountButton.textContent = copy.button_label || DEFAULT_BOOKING_SUCCESS_BANNER.button_label;
    accountButton.style.cssText = 'margin-top: 20px; display: inline-block; text-align: left;';
    messageContainer.appendChild(accountButton);

    overlay.appendChild(messageContainer);
    document.body.appendChild(overlay);

    if (form) {
      form.style.display = 'none';
    }
  }
}

/**
 * Validation of massage booking form data
 * @param {Object} formData Form data
 * @param {{ skipServiceFields?: boolean }} [options]
 * @returns {Object} {valid: boolean, errors: Object}
 */
function validateMassageForm(formData, options = {}) {
  const errors = {};
  const skipService = options.skipServiceFields === true;

  // Validation in the correct sequence:
  // 1. First type (type of massage)
  if (!skipService) {
    if (!formData.type || !formData.type.trim()) {
      errors.type = 'Massage type is required';
    }

    // 2. Then duration
    if (!formData.duration || !formData.duration.trim()) {
      errors.duration = 'Duration is required';
    } else {
      const comboErr = validateMassageServiceCombo(formData.type, formData.duration);
      if (comboErr) {
        errors.duration = comboErr;
      }
    }
  }

  // 3. Then date (date)
  if (!formData.date || !formData.date.trim()) {
    errors.date = 'Date is required';
  } else {
    // Checking that the date is not in the past
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

  // 4. Then time (time)
  if (!formData.time || !formData.time.trim()) {
    errors.time = 'Time is required';
  } else {
    // We check that the time is within the acceptable range (9:00 - 21:00)
    const timeValue = formData.time;
    const [hours, minutes] = timeValue.split(':').map(Number);
    const timeInMinutes = hours * 60 + minutes;
    const minTime = 9 * 60; // 9:00 AM
    const maxTime = 21 * 60; // 9:00 PM
    
    if (timeInMinutes < minTime || timeInMinutes > maxTime) {
      errors.time = 'Massage appointments are only available between 9:00 AM and 9:00 PM';
    }
  }

  // 5. Then name
  if (!formData.name || !formData.name.trim()) {
    errors.name = 'Name is required';
  }

  // 6. Then email (mail)
  if (!formData.email || !formData.email.trim()) {
    errors.email = 'Email is required';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Invalid email address';
  }

  // 7. Then phone (telephone)
  if (!formData.phone || !formData.phone.trim()) {
    errors.phone = 'Phone number is required';
  } else {
    // Checking the phone format (minimum 10 digits)
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
 * Processing the massage booking form
 * @param {HTMLFormElement} form Form element
 * @returns {Promise<boolean>} Processing success
 */
async function handleMassageForm(form) {
  try {
    console.log('MassageAPI.handleMassageForm: Starting...');

    if (form.checkValidity) {
      form.setAttribute('novalidate', '');
    }

    const formData = {
      type: form.querySelector('#type')?.value || '',
      duration: form.querySelector('#duration')?.value || '',
      date: form.querySelector('#date')?.value || '',
      time: form.querySelector('#time')?.value || '',
      name: form.querySelector('#name')?.value || '',
      email: form.querySelector('#email')?.value || '',
      phone: form.querySelector('#phone')?.value || '',
      withRoom: ''
    };

    const cartLines = getMassageCartLines();
    const useCart = getMassageCartTotalCount() > 0;

    console.log('MassageAPI.handleMassageForm: Collected form data:', formData, 'useCart:', useCart, cartLines);

    const validation = validateMassageForm(formData, { skipServiceFields: useCart });
    console.log('MassageAPI: Validation result:', validation);
    if (!validation.valid) {
      console.error('MassageAPI: Validation failed:', validation.errors);

      const typeEl = form.querySelector('#type');
      const durationEl = form.querySelector('#duration');
      const dateEl = form.querySelector('#date');
      const timeEl = form.querySelector('#time');
      const nameEl = form.querySelector('#name');
      const emailEl = form.querySelector('#email');
      const phoneEl = form.querySelector('#phone');

      if (!useCart && validation.errors.type && typeEl) {
        if (typeEl.tagName === 'SELECT' || typeEl.getAttribute('type') !== 'hidden') {
          window.showFieldError(typeEl, validation.errors.type);
          typeEl.classList.add('flash-invalid');
          typeEl.focus();
        } else {
          showFormError(
            form,
            'services',
            'Please add at least one service by tapping a price on a card above, then choose date and time.'
          );
          const scrollTo =
            document.getElementById('massage-form') || document.getElementById('book');
          if (scrollTo) scrollTo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
      }

      if (!useCart && validation.errors.duration && durationEl) {
        if (durationEl.tagName === 'SELECT' || durationEl.getAttribute('type') !== 'hidden') {
          window.showFieldError(durationEl, validation.errors.duration);
          durationEl.classList.add('flash-invalid');
          durationEl.focus();
        } else {
          showFormError(
            form,
            'services',
            'Please add at least one service by tapping a price on a card above, then choose date and time.'
          );
          const scrollTo =
            document.getElementById('massage-form') || document.getElementById('book');
          if (scrollTo) scrollTo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
      }

      if (validation.errors.date && dateEl) {
        window.showFieldError(dateEl, validation.errors.date);
        dateEl.classList.add('flash-invalid');
        dateEl.focus();
        return false;
      }

      if (validation.errors.time && timeEl) {
        window.showFieldError(timeEl, validation.errors.time);
        timeEl.classList.add('flash-invalid');
        timeEl.focus();
        return false;
      }

      if (validation.errors.name && nameEl) {
        window.showFieldError(nameEl, validation.errors.name);
        nameEl.classList.add('flash-invalid');
        nameEl.focus();
        return false;
      }

      if (validation.errors.email && emailEl) {
        window.showFieldError(emailEl, validation.errors.email);
        emailEl.classList.add('flash-invalid');
        emailEl.focus();
        return false;
      }

      if (validation.errors.phone && phoneEl) {
        window.showFieldError(phoneEl, validation.errors.phone);
        phoneEl.classList.add('flash-invalid');
        phoneEl.focus();
        return false;
      }

      return false;
    }

    clearFormErrors(form);

    const afterMassageBookSuccess = () => {
      const reminder = document.getElementById('room-reminder');
      if (reminder) {
        const orders = JSON.parse(localStorage.getItem('btb_orders') || '[]');
        const hasRoomOrder = orders.some((order) => order.kind === 'room');
        if (!hasRoomOrder) {
          localStorage.setItem('btb_room_reminder_shown', '1');
          reminder.style.display = 'block';
          reminder.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    };

    try {
      if (useCart) {
        console.log('MassageAPI: Creating massage bookings from cart...', cartLines);
        let created = 0;
        for (let li = 0; li < cartLines.length; li += 1) {
          const line = cartLines[li];
          for (let i = 0; i < line.qty; i += 1) {
            const payload = {
              type: line.type,
              duration: line.duration,
              date: formData.date,
              time: formData.time,
              name: formData.name,
              email: formData.email,
              phone: formData.phone,
              withRoom: formData.withRoom
            };
            const result = await postSingleMassageBookingRequest(payload);
            created += 1;
            appendMassageOrderToLocalStorage(result, payload);
          }
        }
        clearMassageBookingCart();
        renderMassageCartUI(form);
        form.reset();
        if (typeof prefillContact === 'function') {
          prefillContact(form);
        }
        afterMassageBookSuccess();
        const isAuthCart = window.authSystem && window.authSystem.isAuthenticated;
        await showBookingSuccessMessage(form, {
          isAuthenticated: isAuthCart,
          bookingData: {
            name: formData.name,
            email: formData.email,
            phone: formData.phone
          }
        });
        return true;
      }

      console.log('MassageAPI: Creating single massage booking...', formData);
      const singleResult = await postSingleMassageBookingRequest(formData);
      appendMassageOrderToLocalStorage(singleResult, formData);

      afterMassageBookSuccess();

      form.reset();
      if (typeof prefillContact === 'function') {
        prefillContact(form);
      }
      renderMassageCartUI(form);
      const isAuthSingle = window.authSystem && window.authSystem.isAuthenticated;
      await showBookingSuccessMessage(form, {
        isAuthenticated: isAuthSingle,
        bookingData: {
          name: formData.name,
          email: formData.email,
          phone: formData.phone
        }
      });
      return true;
    } catch (error) {
      console.error('MassageAPI: Failed to create massage booking:', error);
      showFormError(
        form,
        'submit',
        error.message || 'Failed to create massage booking. Please try again.'
      );
      return false;
    }
  } catch (error) {
    console.error('MassageAPI.handleMassageForm: Exception caught:', error);
    showFormError(form, 'submit', error.message || 'An error occurred');
    return false;
  }
}

// Exporting functions for use in other modules
window.BookingAPI = {
  checkAvailability,
  createBooking,
  getBooking,
  validateBookingForm,
  handleBookingForm,
  validateMassageForm,
  handleMassageForm,
  addMassageCartLine,
  clearMassageBookingCart,
  getMassageCartLines,
  renderMassageCartUI,
  showFormErrors,
  showFormError,
  clearFormErrors,
  showAuthSuccessMessage
};

// Exporting a function for use in other modules
window.showAuthSuccessMessage = showAuthSuccessMessage;

