// Admin Bookings Management JavaScript

// Check if user is authenticated
function checkAdminAuth() {
  const isAuthenticated = localStorage.getItem('btb_admin_auth') === 'true';
  if (!isAuthenticated && !window.location.pathname.includes('admin-login')) {
    window.location.href = 'admin-login.html';
  }
}

// Logout function
function adminLogout() {
  localStorage.removeItem('btb_admin_auth');
  window.location.href = 'admin-login.html';
}

// Navigation between sections (tabs)
function showSection(sectionName) {
  // Hide all sections
  document.querySelectorAll('.admin-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Remove active class from all nav tabs
  document.querySelectorAll('.admin-nav-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected section
  const targetSection = document.getElementById(`${sectionName}-section`);
  if (targetSection) {
    targetSection.style.display = 'block';
  }
  
  // Add active class to clicked tab
  const activeTab = document.querySelector(`[data-section="${sectionName}"]`);
  if (activeTab) {
    activeTab.classList.add('active');
  }
  
  // Load section data
  loadSectionData(sectionName);
}

// Load data for specific section
function loadSectionData(sectionName) {
  switch(sectionName) {
    case 'bookings':
      loadBookingsData();
      initBookingsFilters();
      break;
    case 'calendar':
      loadCalendarData();
      loadBlockedDates();
      initCalendarBlocking();
      break;
    case 'dashboard':
      initDashboardFilters();
      updateDashboardStats();
      break;
  }
}

// ==========================================
// BOOKINGS MANAGEMENT
// ==========================================

// Load bookings data
async function loadBookingsData() {
  const loadingEl = document.getElementById('bookings-loading');
  const listEl = document.getElementById('bookings-list');
  const emptyEl = document.getElementById('bookings-empty');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (listEl) listEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  
  try {
    // Get filters
    const status = document.getElementById('bookings-filter-status')?.value || '';
    const room = document.getElementById('bookings-filter-room')?.value || '';
    const dateFrom = document.getElementById('bookings-filter-date-from')?.value || '';
    const dateTo = document.getElementById('bookings-filter-date-to')?.value || '';
    
    // Build query params
    const params = new URLSearchParams({ action: 'get_bookings' });
    if (status) params.append('status', status);
    if (room) params.append('room_name', room);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load bookings');
    }
    
    const result = await response.json();
    
    if (loadingEl) loadingEl.style.display = 'none';
    
    if (result.success && result.data?.bookings && result.data.bookings.length > 0) {
      if (listEl) {
        listEl.style.display = 'block';
        renderBookingsList(result.data.bookings);
      }
    } else {
      if (emptyEl) emptyEl.style.display = 'block';
    }
  } catch (error) {
    console.error('Load bookings error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    if (emptyEl) emptyEl.style.display = 'block';
    showStatus('Failed to load bookings: ' + error.message, 'error');
  }
}

// Render bookings list
function renderBookingsList(bookings) {
  const listEl = document.getElementById('bookings-list');
  if (!listEl) return;
  
  listEl.innerHTML = '';
  
  bookings.forEach(booking => {
    const card = document.createElement('div');
    card.className = 'booking-card';
    
    const statusClass = booking.status || 'pending';
    const paymentStatusClass = booking.payment_status || 'pending';
    
    card.innerHTML = `
      <div class="booking-card-header">
        <div>
          <h3 style="margin: 0 0 8px 0;">Booking #${booking.id || '—'}</h3>
          <div style="display: flex; align-items: center;">
            <span class="booking-status-badge ${statusClass}">${statusClass}</span>
            <span class="payment-status-badge ${paymentStatusClass}">${paymentStatusClass}</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 12px; color: #718096; margin-bottom: 4px;">Confirmation Code</div>
          <div style="font-family: monospace; font-weight: 600;">${booking.confirmation_code || '—'}</div>
        </div>
      </div>
      
      <div class="booking-details-grid">
        <div class="booking-detail-item">
          <div class="booking-detail-label">Room</div>
          <div class="booking-detail-value">${escapeHtml(booking.room_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Check-in</div>
          <div class="booking-detail-value">${formatDate(booking.checkin_date)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Check-out</div>
          <div class="booking-detail-value">${formatDate(booking.checkout_date)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guests</div>
          <div class="booking-detail-value">${booking.guests_count || '—'}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Pets</div>
          <div class="booking-detail-value">${booking.pets ? 'Yes' : 'No'}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Total Amount</div>
          <div class="booking-detail-value">${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Guest Name</div>
          <div class="booking-detail-value">${escapeHtml(booking.guest_name || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Email</div>
          <div class="booking-detail-value">${escapeHtml(booking.email || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Phone</div>
          <div class="booking-detail-value">${escapeHtml(booking.phone || '—')}</div>
        </div>
        <div class="booking-detail-item">
          <div class="booking-detail-label">Created</div>
          <div class="booking-detail-value">${formatDateTime(booking.created_at)}</div>
        </div>
      </div>
      
      <div class="booking-actions">
        ${booking.status === 'pending' ? `
          <button class="admin-btn admin-btn-primary" onclick="confirmBooking(${booking.id})">Confirm</button>
          <button class="admin-btn admin-btn-warning" onclick="rejectBooking(${booking.id})">Reject</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteBooking(${booking.id})">Delete</button>
        ` : ''}
        ${booking.status === 'confirmed' && booking.payment_status !== 'paid' ? `
          <button class="admin-btn admin-btn-danger" onclick="cancelBooking(${booking.id})">Cancel</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteBooking(${booking.id})">Delete</button>
        ` : ''}
        ${booking.status === 'confirmed' ? `
          <button class="admin-btn admin-btn-secondary" onclick="viewBookingDetails(${booking.id})">View Details</button>
          <button class="admin-btn admin-btn-danger" onclick="deleteBooking(${booking.id})">Delete</button>
        ` : ''}
        ${booking.status === 'cancelled' ? `
          <button class="admin-btn admin-btn-danger" onclick="deleteBooking(${booking.id})">Delete</button>
        ` : ''}
      </div>
    `;
    
    listEl.appendChild(card);
  });
}

// Confirm booking
async function confirmBooking(bookingId) {
  if (!confirm('Are you sure you want to confirm this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'confirm_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to confirm booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking confirmed successfully!');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to confirm booking');
    }
  } catch (error) {
    console.error('Confirm booking error:', error);
    showStatus('Failed to confirm booking: ' + error.message, 'error');
  }
}

// Reject booking (for pending bookings - same as cancel but clearer intent)
async function rejectBooking(bookingId) {
  const reason = prompt('Please enter a reason for rejection (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to reject this booking? The dates will become available for other guests.')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to reject booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking rejected successfully! Dates are now available.');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to reject booking');
    }
  } catch (error) {
    console.error('Reject booking error:', error);
    showStatus('Failed to reject booking: ' + error.message, 'error');
  }
}

// Cancel booking
async function cancelBooking(bookingId) {
  const reason = prompt('Please enter a reason for cancellation (optional):');
  if (reason === null) {
    return; // User cancelled
  }
  
  if (!confirm('Are you sure you want to cancel this booking?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to cancel booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking cancelled successfully!');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to cancel booking');
    }
  } catch (error) {
    console.error('Cancel booking error:', error);
    showStatus('Failed to cancel booking: ' + error.message, 'error');
  }
}

// Delete booking (permanently remove from database)
async function deleteBooking(bookingId) {
  if (!confirm('Are you sure you want to PERMANENTLY DELETE this booking? This action cannot be undone.')) {
    return;
  }
  
  if (!confirm('This will permanently delete the booking from the database. Continue?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'delete_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to delete booking');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Booking deleted successfully!');
      loadBookingsData();
      updateDashboardStats();
    } else {
      throw new Error(result.error || 'Failed to delete booking');
    }
  } catch (error) {
    console.error('Delete booking error:', error);
    showStatus('Failed to delete booking: ' + error.message, 'error');
  }
}

// View booking details
async function viewBookingDetails(bookingId) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_booking');
    formData.append('booking_id', bookingId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to load booking details');
    }
    
    const result = await response.json();
    
    if (result.success && result.data?.booking) {
      const booking = result.data.booking;
      alert(`Booking Details:\n\n` +
        `ID: ${booking.id}\n` +
        `Room: ${booking.room_name}\n` +
        `Check-in: ${formatDate(booking.checkin_date)}\n` +
        `Check-out: ${formatDate(booking.checkout_date)}\n` +
        `Guests: ${booking.guests_count}\n` +
        `Pets: ${booking.pets ? 'Yes' : 'No'}\n` +
        `Guest: ${booking.guest_name}\n` +
        `Email: ${booking.email}\n` +
        `Phone: ${booking.phone}\n` +
        `Total: ${booking.currency || 'CAD'} ${parseFloat(booking.total_amount || 0).toFixed(2)}\n` +
        `Status: ${booking.status}\n` +
        `Payment: ${booking.payment_status || 'pending'}\n` +
        `Confirmation Code: ${booking.confirmation_code || '—'}\n` +
        `Created: ${formatDateTime(booking.created_at)}\n`
      );
    }
  } catch (error) {
    console.error('View booking details error:', error);
    showStatus('Failed to load booking details: ' + error.message, 'error');
  }
}

// Initialize bookings filters
function initBookingsFilters() {
  const applyBtn = document.getElementById('bookings-filter-apply');
  const resetBtn = document.getElementById('bookings-filter-reset');
  const refreshBtn = document.getElementById('bookings-refresh');
  
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
  
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      document.getElementById('bookings-filter-status').value = '';
      document.getElementById('bookings-filter-room').value = '';
      document.getElementById('bookings-filter-date-from').value = '';
      document.getElementById('bookings-filter-date-to').value = '';
      loadBookingsData();
    });
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadBookingsData();
    });
  }
}

// ==========================================
// CALENDAR MANAGEMENT
// ==========================================

// Load calendar data
async function loadCalendarData() {
  const loadingEl = document.getElementById('admin-calendar-loading');
  const gridEl = document.getElementById('admin-calendar-grid');
  
  if (loadingEl) loadingEl.style.display = 'block';
  if (gridEl) gridEl.style.display = 'none';
  
  try {
    const roomSelect = document.getElementById('calendar-room-select');
    const selectedRoom = roomSelect ? roomSelect.value : '';
    
    // Get bookings for selected room or all rooms
    const params = new URLSearchParams({ action: 'get_bookings', status: 'confirmed,pending' });
    if (selectedRoom) {
      params.append('room_name', selectedRoom);
    }
    
    const bookingsResponse = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    // Get blocked dates (manual blocking)
    const blockedParams = new URLSearchParams({ action: 'get_blocked_dates' });
    if (selectedRoom) {
      blockedParams.append('room_name', selectedRoom);
    }
    
    const blockedResponse = await fetch('api.php?' + blockedParams.toString(), {
      method: 'GET'
    });
    
    let bookings = [];
    if (bookingsResponse.ok) {
      const bookingsResult = await bookingsResponse.json();
      if (bookingsResult.success && bookingsResult.data?.bookings) {
        bookings = bookingsResult.data.bookings;
      }
    }
    
    let blockedDates = [];
    if (blockedResponse.ok) {
      const blockedResult = await blockedResponse.json();
      if (blockedResult.success) {
        // Получаем ручные блокировки
        if (blockedResult.data?.blocked_dates) {
          blockedDates = blockedResult.data.blocked_dates.map(b => b.blocked_date);
        }
        
        // Также получаем Airbnb заблокированные даты
        if (blockedResult.data?.airbnb_blocked_dates) {
          blockedDates = [...blockedDates, ...blockedResult.data.airbnb_blocked_dates];
        }
      }
    }
    
    // Убираем дубликаты
    blockedDates = [...new Set(blockedDates)];
    
    if (loadingEl) loadingEl.style.display = 'none';
    if (gridEl) {
      gridEl.style.display = 'grid';
      renderAdminCalendar(gridEl, bookings, blockedDates);
    }
  } catch (error) {
    console.error('Load calendar error:', error);
    if (loadingEl) loadingEl.style.display = 'none';
    showStatus('Failed to load calendar: ' + error.message, 'error');
  }
}

// Render admin calendar
function renderAdminCalendar(container, bookings, blockedDates) {
  container.innerHTML = '';
  container.className = 'admin-calendar-grid';
  
  const today = new Date();
  const months = [];
  for (let i = 0; i < 3; i++) {
    const date = new Date(today.getFullYear(), today.getMonth() + i, 1);
    months.push(date);
  }
  
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  
  months.forEach((monthDate, monthIndex) => {
    const monthYear = monthDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();
    
    // Month header
    const monthHeader = document.createElement('div');
    monthHeader.className = 'admin-calendar-month';
    monthHeader.textContent = monthYear;
    container.appendChild(monthHeader);
    
    // Day headers
    weekdays.forEach(day => {
      const dayHeader = document.createElement('div');
      dayHeader.className = 'admin-calendar-day-header';
      dayHeader.textContent = day;
      container.appendChild(dayHeader);
    });
    
    // Empty cells for days before first day of month
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'admin-calendar-day';
      emptyCell.style.visibility = 'hidden';
      container.appendChild(emptyCell);
    }
    
    // Days
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      const dateString = formatDateString(date);
      
      const dayCell = document.createElement('div');
      dayCell.className = 'admin-calendar-day';
      dayCell.textContent = day;
      
      const todayDate = new Date();
      todayDate.setHours(0, 0, 0, 0);
      const cellDate = new Date(date);
      cellDate.setHours(0, 0, 0, 0);
      
      if (cellDate < todayDate) {
        dayCell.classList.add('past');
      } else if (blockedDates.includes(dateString)) {
        dayCell.classList.add('blocked');
      } else if (isDateBooked(dateString, bookings)) {
        dayCell.classList.add('booked');
        dayCell.classList.add('has-booking');
      } else {
        dayCell.classList.add('available');
      }
      
      container.appendChild(dayCell);
    }
  });
}

// Block date
async function blockDate() {
  const roomSelect = document.getElementById('block-room-select');
  const dateInput = document.getElementById('block-date');
  const reasonInput = document.getElementById('block-reason');
  
  if (!roomSelect || !dateInput) {
    showStatus('Please fill in all required fields', 'error');
    return;
  }
  
  const roomName = roomSelect.value;
  const date = dateInput.value;
  const reason = reasonInput ? reasonInput.value : '';
  
  if (!roomName || !date) {
    showStatus('Please select room and date', 'error');
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'block_dates');
    formData.append('room_name', roomName);
    formData.append('dates[]', date);
    if (reason) {
      formData.append('reason', reason);
    }
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to block date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date blocked successfully!');
      if (dateInput) dateInput.value = '';
      if (reasonInput) reasonInput.value = '';
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to block date');
    }
  } catch (error) {
    console.error('Block date error:', error);
    showStatus('Failed to block date: ' + error.message, 'error');
  }
}

// Unblock date
async function unblockDate(blockedDateId) {
  if (!confirm('Are you sure you want to unblock this date?')) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('action', 'unblock_dates');
    formData.append('blocked_date_id', blockedDateId);
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to unblock date');
    }
    
    const result = await response.json();
    
    if (result.success) {
      showStatus('Date unblocked successfully!');
      loadBlockedDates();
      loadCalendarData();
    } else {
      throw new Error(result.error || 'Failed to unblock date');
    }
  } catch (error) {
    console.error('Unblock date error:', error);
    showStatus('Failed to unblock date: ' + error.message, 'error');
  }
}

// Load blocked dates
async function loadBlockedDates() {
  const listEl = document.getElementById('blocked-dates-list');
  if (!listEl) return;
  
  try {
    const params = new URLSearchParams({ action: 'get_blocked_dates' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (!response.ok) {
      throw new Error('Failed to load blocked dates');
    }
    
    const result = await response.json();
    
    listEl.innerHTML = '';
    
    if (result.success && result.data?.blocked_dates && result.data.blocked_dates.length > 0) {
      result.data.blocked_dates.forEach(blocked => {
        const item = document.createElement('div');
        item.className = 'blocked-date-item';
        item.innerHTML = `
          <div>
            <strong>${escapeHtml(blocked.room_name || '—')}</strong>
            <div style="font-size: 14px; color: #718096; margin-top: 4px;">
              ${formatDate(blocked.blocked_date)}
              ${blocked.reason ? ` — ${escapeHtml(blocked.reason)}` : ''}
            </div>
          </div>
          <button class="admin-btn admin-btn-danger" onclick="unblockDate(${blocked.id})">Unblock</button>
        `;
        listEl.appendChild(item);
      });
    } else {
      listEl.innerHTML = '<p style="color: #718096; text-align: center; padding: 20px;">No blocked dates</p>';
    }
  } catch (error) {
    console.error('Load blocked dates error:', error);
    listEl.innerHTML = '<p style="color: #e53e3e;">Failed to load blocked dates</p>';
  }
}

// Initialize calendar blocking
function initCalendarBlocking() {
  const blockBtn = document.getElementById('block-date-btn');
  const refreshBtn = document.getElementById('calendar-refresh');
  const roomSelect = document.getElementById('calendar-room-select');
  const syncBtn = document.getElementById('airbnb-sync-btn');
  
  if (blockBtn) {
    blockBtn.addEventListener('click', blockDate);
  }
  
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      loadCalendarData();
    });
  }
  
  if (roomSelect) {
    roomSelect.addEventListener('change', () => {
      loadCalendarData();
    });
  }
  
  if (syncBtn) {
    syncBtn.addEventListener('click', syncAirbnbCalendar);
  }
  
  // Загружаем статус синхронизации при открытии раздела
  loadAirbnbSyncStatus();
}

// Синхронизация календаря Airbnb
async function syncAirbnbCalendar() {
  const syncBtn = document.getElementById('airbnb-sync-btn');
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!syncBtn || !statusEl || !statusTextEl) {
    return;
  }
  
  // Показываем состояние загрузки
  syncBtn.disabled = true;
  syncBtn.textContent = 'Syncing...';
  statusEl.style.display = 'block';
  statusTextEl.textContent = 'Syncing with Airbnb calendar...';
  statusTextEl.style.color = '#4299e1';
  
  try {
    const formData = new FormData();
    formData.append('action', 'sync_airbnb');
    
    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error('Failed to sync Airbnb calendar');
    }
    
    const result = await response.json();
    
    if (result.success) {
      statusTextEl.textContent = `Sync completed successfully! ${result.data?.synced_rooms?.length || 0} room(s) synced.`;
      statusTextEl.style.color = '#2f855a';
      
      // Если были ошибки, показываем их
      if (result.data?.errors && result.data.errors.length > 0) {
        const errors = result.data.errors.map(e => `${e.room}: ${e.error}`).join(', ');
        statusTextEl.textContent += ` Errors: ${errors}`;
        statusTextEl.style.color = '#e53e3e';
      }
      
      // Обновляем календарь после синхронизации
      setTimeout(() => {
        loadCalendarData();
        loadAirbnbSyncStatus();
      }, 1000);
    } else {
      throw new Error(result.error || 'Sync failed');
    }
  } catch (error) {
    console.error('Sync Airbnb error:', error);
    statusTextEl.textContent = 'Failed to sync Airbnb calendar: ' + error.message;
    statusTextEl.style.color = '#e53e3e';
  } finally {
    syncBtn.disabled = false;
    syncBtn.textContent = 'Sync with Airbnb';
  }
}

// Загрузка статуса синхронизации Airbnb
async function loadAirbnbSyncStatus() {
  const statusEl = document.getElementById('airbnb-sync-status');
  const statusTextEl = document.getElementById('airbnb-sync-status-text');
  
  if (!statusEl || !statusTextEl) {
    return;
  }
  
  try {
    const params = new URLSearchParams({ action: 'get_airbnb_sync_status' });
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      
      if (result.success && result.data?.sync_status && result.data.sync_status.length > 0) {
        const status = result.data.sync_status;
        const statusText = status.map(s => {
          const lastSync = s.last_synced ? new Date(s.last_synced).toLocaleString() : 'Never';
          return `${s.room_name}: ${s.blocked_count} blocked dates, last synced: ${lastSync}`;
        }).join('<br>');
        
        statusTextEl.innerHTML = `<strong>Last Sync Status:</strong><br>${statusText}`;
        statusTextEl.style.color = '#4a5568';
        statusEl.style.display = 'block';
      } else {
        statusTextEl.textContent = 'No sync status available. Click "Sync with Airbnb" to start syncing.';
        statusTextEl.style.color = '#718096';
        statusEl.style.display = 'block';
      }
    }
  } catch (error) {
    console.error('Load sync status error:', error);
    // Не показываем ошибку, просто скрываем статус
    statusEl.style.display = 'none';
  }
}

// ==========================================
// DASHBOARD STATS
// ==========================================

// Initialize dashboard filters
function initDashboardFilters() {
  // Set current month and year as default
  const monthSelect = document.getElementById('dashboard-month');
  const yearSelect = document.getElementById('dashboard-year');
  
  if (monthSelect) {
    const currentMonth = String(new Date().getMonth() + 1).padStart(2, '0');
    monthSelect.value = currentMonth;
  }
  
  if (yearSelect) {
    // Populate years (current year ± 2 years)
    const currentYear = new Date().getFullYear();
    yearSelect.innerHTML = '';
    
    for (let year = currentYear - 2; year <= currentYear + 2; year++) {
      const option = document.createElement('option');
      option.value = year;
      option.textContent = year;
      if (year === currentYear) {
        option.selected = true;
      }
      yearSelect.appendChild(option);
    }
  }
  
  // Apply filter button
  const applyBtn = document.getElementById('dashboard-apply-filter');
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      updateDashboardStats();
    });
  }
  
  // Auto-apply on month/year change
  if (monthSelect) {
    monthSelect.addEventListener('change', () => {
      updateDashboardStats();
    });
  }
  
  if (yearSelect) {
    yearSelect.addEventListener('change', () => {
      updateDashboardStats();
    });
  }
}

// Update dashboard statistics
async function updateDashboardStats() {
  try {
    // Get selected month and year
    const monthSelect = document.getElementById('dashboard-month');
    const yearSelect = document.getElementById('dashboard-year');
    
    const selectedMonth = monthSelect ? monthSelect.value : new Date().getMonth() + 1;
    const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
    
    // Calculate date range for selected month/year
    const startDate = `${selectedYear}-${selectedMonth}-01`;
    const daysInMonth = new Date(selectedYear, parseInt(selectedMonth), 0).getDate();
    const endDate = `${selectedYear}-${selectedMonth}-${daysInMonth}`;
    
    // Build query params with date filter
    const params = new URLSearchParams({ 
      action: 'get_bookings',
      date_from: startDate,
      date_to: endDate
    });
    
    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success && result.data?.bookings) {
        const bookings = result.data.bookings;
        const pendingCount = bookings.filter(b => b.status === 'pending').length;
        const totalCount = bookings.length;
        const confirmedCount = bookings.filter(b => b.status === 'confirmed').length;
        const totalRevenue = bookings
          .filter(b => b.status === 'confirmed' && b.payment_status === 'paid')
          .reduce((sum, b) => sum + parseFloat(b.total_amount || 0), 0);
        
        const pendingEl = document.getElementById('pending-bookings');
        const totalEl = document.getElementById('total-bookings');
        const confirmedEl = document.getElementById('confirmed-bookings');
        const revenueEl = document.getElementById('total-revenue');
        
        if (pendingEl) pendingEl.textContent = pendingCount;
        if (totalEl) totalEl.textContent = totalCount;
        if (confirmedEl) confirmedEl.textContent = confirmedCount;
        if (revenueEl) revenueEl.textContent = totalRevenue.toFixed(2) + ' CAD';
      }
    }
  } catch (error) {
    console.error('Update dashboard stats error:', error);
  }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateString) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateString) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function isDateBooked(dateString, bookings) {
  const checkDate = new Date(dateString + 'T00:00:00');
  
  return bookings.some(booking => {
    const checkin = new Date(booking.checkin_date + 'T00:00:00');
    const checkout = new Date(booking.checkout_date + 'T00:00:00');
    
    // Дата забронирована, если она в диапазоне checkin (включительно) до checkout (исключительно)
    return checkDate >= checkin && checkDate < checkout;
  });
}

function showStatus(message, type = 'success') {
  // Simple status notification
  const statusDiv = document.createElement('div');
  statusDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: ${type === 'error' ? '#e53e3e' : '#2f855a'};
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 10000;
    font-weight: 600;
  `;
  statusDiv.textContent = message;
  document.body.appendChild(statusDiv);
  
  setTimeout(() => {
    statusDiv.remove();
  }, 3000);
}

// ==========================================
// INITIALIZATION
// ==========================================

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  checkAdminAuth();
  
  // Navigation tabs
  document.querySelectorAll('.admin-nav-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const section = tab.getAttribute('data-section');
      if (section) {
        showSection(section);
      }
    });
  });
  
  // Logout button
  const logoutBtn = document.getElementById('admin-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      adminLogout();
    });
  }
  
  // Show dashboard by default
  showSection('dashboard');
});

// Export functions for global access
window.confirmBooking = confirmBooking;
window.rejectBooking = rejectBooking;
window.cancelBooking = cancelBooking;
window.deleteBooking = deleteBooking;
window.viewBookingDetails = viewBookingDetails;
window.unblockDate = unblockDate;

