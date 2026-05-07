/**
 * Availability Calendar Widget
 * Availability calendar widget for room pages
 */

/**
 * Create an Availability Calendar Widget
 * @param {string} containerSelector Container selector for the calendar
 * @param {string} roomName Room name
 */
function createAvailabilityCalendar(containerSelector, roomName) {
  const container = document.querySelector(containerSelector);
  if (!container) {
    console.error('Calendar container not found:', containerSelector);
    return;
  }

  // Creating a calendar
  container.innerHTML = `
    <div class="availability-calendar">
      <h3>Availability Calendar</h3>
      <div class="calendar-loading">Loading availability...</div>
      <div class="calendar-grid" id="calendar-grid-${roomName.replace(/\s+/g, '-')}" style="display: none;"></div>
      <div class="calendar-legend" style="margin-top: 20px;">
        <div class="legend-item">
          <span class="legend-color available"></span>
          <span>Available</span>
        </div>
        <div class="legend-item">
          <span class="legend-color booked"></span>
          <span>Booked</span>
        </div>
        <div class="legend-item">
          <span class="legend-color blocked"></span>
          <span>Blocked</span>
        </div>
      </div>
    </div>
  `;

  // Adding styles
  addCalendarStyles();

  // Loading availability
  loadAvailability(roomName, container);
}

/**
 * Loading accessibility for a room
 * @param {string} roomName Room name
 * @param {HTMLElement} container Calendar container
 */
async function loadAvailability(roomName, container) {
  try {
    // Getting blocked dates (getBlockedDates already returns a combined list
    // including manual blocking and Airbnb blocking)
    const blockedDates = await getBlockedDates(roomName);
    
    // Receiving existing bookings
    const bookings = await getBookingsForRoom(roomName);

    // Create a calendar for 3 months ahead
    const today = new Date();
    const months = [];
    for (let i = 0; i < 3; i++) {
      const date = new Date(today.getFullYear(), today.getMonth() + i, 1);
      months.push(date);
    }

    // Generating a calendar
    generateCalendar(container, months, blockedDates, bookings, roomName);

  } catch (error) {
    console.error('Load availability error:', error);
    const loadingEl = container.querySelector('.calendar-loading');
    if (loadingEl) {
      loadingEl.textContent = 'Failed to load availability';
    }
  }
}

/**
 * Getting blocked dates for a room (manual blocking)
 * @param {string} roomName Room name
 * @returns {Promise<Array>} Array of blocked dates
 */
async function getBlockedDates(roomName) {
  try {
    const formData = new FormData();
    formData.append('action', 'get_blocked_dates');
    formData.append('room_name', roomName);

    const response = await fetch('api.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      return [];
    }

    const result = await response.json();
    let blockedDates = [];
    
    if (result.success && result.data?.blocked_dates) {
      blockedDates = result.data.blocked_dates.map(block => block.blocked_date);
    }
    
    // We also receive Airbnb blocked dates
    if (result.success && result.data?.airbnb_blocked_dates) {
      blockedDates = [...blockedDates, ...result.data.airbnb_blocked_dates];
    }

    return blockedDates;
  } catch (error) {
    console.error('Get blocked dates error:', error);
    return [];
  }
}

/**
 * Getting Blocked Dates from Airbnb for a Room
 * @param {string} roomName Room name
 * @returns {Promise<Array>} Array of blocked dates from Airbnb
 */
async function getAirbnbBlockedDates(roomName) {
  try {
    // Airbnb blocked dates are now returned via get_blocked_dates
    // in the airbnb_blocked_dates field
    // Therefore, this function is no longer needed - we use getBlockedDates which combines both lists
    return [];
  } catch (error) {
    console.error('Get Airbnb blocked dates error:', error);
    return [];
  }
}

/**
 * Receiving room reservations
 * @param {string} roomName Room name
 * @returns {Promise<Array>} Array of reservations
 */
async function getBookingsForRoom(roomName) {
  try {
    // We use a GET request for public access
    // FIXED: Showing only confirmed bookings as occupied
    // pending bookings DO NOT block dates for other clients
    const params = new URLSearchParams({
      action: 'get_bookings',
      room_name: roomName,
      status: 'confirmed' // Only confirmed bookings block dates
    });

    const response = await fetch('api.php?' + params.toString(), {
      method: 'GET'
    });

    if (!response.ok) {
      return [];
    }

    const result = await response.json();
    if (result.success && result.data?.bookings) {
      return result.data.bookings;
    }

    return [];
  } catch (error) {
    console.error('Get bookings error:', error);
    return [];
  }
}

/**
 * Calendar generation
 * @param {HTMLElement} container Calendar container
 * @param {Array<Date>} months Array of months to display
 * @param {Array<string>} blockedDates Blocked dates
 * @param {Array<Object>} bookings Reservations
 * @param {string} roomName Room name
 */
function generateCalendar(container, months, blockedDates, bookings, roomName) {
  const grid = container.querySelector(`#calendar-grid-${roomName.replace(/\s+/g, '-')}`);
  const loadingEl = container.querySelector('.calendar-loading');
  
  if (!grid) return;

  grid.innerHTML = '';
  if (loadingEl) {
    loadingEl.style.display = 'none';
  }
  grid.style.display = 'grid';

  months.forEach((monthDate, monthIndex) => {
    const monthYear = monthDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const firstDay = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1).getDay();

    // Creating a Month Header
    const monthHeader = document.createElement('div');
    monthHeader.className = 'calendar-month-header';
    monthHeader.textContent = monthYear;
    grid.appendChild(monthHeader);

    // Creating weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
      const dayHeader = document.createElement('div');
      dayHeader.className = 'calendar-day-header';
      dayHeader.textContent = day;
      grid.appendChild(dayHeader);
    });

    // Create empty cells for days before the first day of the month
    for (let i = 0; i < firstDay; i++) {
      const emptyCell = document.createElement('div');
      emptyCell.className = 'calendar-day-empty';
      grid.appendChild(emptyCell);
    }

    // Creating day cells
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(monthDate.getFullYear(), monthDate.getMonth(), day);
      const dateString = formatDateString(date);
      
      const dayCell = document.createElement('div');
      dayCell.className = 'calendar-day';
      dayCell.textContent = day;

      // Checking date availability
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const cellDate = new Date(date);
      cellDate.setHours(0, 0, 0, 0);

      if (cellDate < today) {
        // Past date
        dayCell.classList.add('past');
      } else if (blockedDates.includes(dateString)) {
        // Blocked date
        dayCell.classList.add('blocked');
      } else if (isDateBooked(dateString, bookings)) {
        // Booked date
        dayCell.classList.add('booked');
      } else {
        // Available date
        dayCell.classList.add('available');
      }

      grid.appendChild(dayCell);
    }
  });
}

/**
 * Checking if the date is booked
 * @param {string} dateString Date in YYYY-MM-DD format
 * @param {Array<Object>} bookings Array of bookings
 * @returns {boolean} Is the date booked?
 */
function isDateBooked(dateString, bookings) {
  const checkDate = new Date(dateString + 'T00:00:00');
  
  return bookings.some(booking => {
    // FIXED: We only take into account confirmed bookings
    // pending bookings should NOT block dates on the calendar
    if (booking.status !== 'confirmed' && booking.status !== 'paid') {
      return false;
    }
    
    const checkin = new Date(booking.checkin_date + 'T00:00:00');
    const checkout = new Date(booking.checkout_date + 'T00:00:00');
    
    // A date is booked if it is in the range checkin (inclusive) to checkout (exclusive)
    return checkDate >= checkin && checkDate < checkout;
  });
}

/**
 * Formatting a date into the string YYYY-MM-DD
 * @param {Date} date Date
 * @returns {string} Formatted date
 */
function formatDateString(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Adding calendar styles
 */
function addCalendarStyles() {
  if (document.getElementById('availability-calendar-styles')) {
    return; // Styles have already been added
  }

  const style = document.createElement('style');
  style.id = 'availability-calendar-styles';
  style.textContent = `
    .availability-calendar {
      margin: 20px 0;
      padding: 20px;
      background: var(--bg-secondary, #f7fafc);
      border-radius: 8px;
    }
    
    .availability-calendar h3 {
      margin-top: 0;
      margin-bottom: 20px;
      color: var(--text-primary, #333);
    }
    
    .calendar-loading {
      text-align: center;
      padding: 20px;
      color: var(--text-secondary, #666);
    }
    
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 4px;
      margin-bottom: 20px;
    }
    
    .calendar-month-header {
      grid-column: 1 / -1;
      font-weight: 600;
      font-size: 18px;
      padding: 10px;
      text-align: center;
      background: var(--bg-primary, #fff);
      border-radius: 4px;
      margin-bottom: 10px;
    }
    
    .calendar-day-header {
      font-weight: 600;
      font-size: 12px;
      text-align: center;
      padding: 8px 4px;
      color: var(--text-secondary, #666);
      text-transform: uppercase;
    }
    
    .calendar-day {
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .calendar-day-empty {
      aspect-ratio: 1;
    }
    
    .calendar-day.past {
      background: var(--bg-tertiary, #e2e8f0);
      color: var(--text-tertiary, #999);
      cursor: not-allowed;
    }
    
    .calendar-day.available {
      background: #c6f6d5;
      color: #2f855a;
      cursor: pointer;
    }
    
    .calendar-day.available:hover {
      background: #9ae6b4;
    }
    
    .calendar-day.booked {
      background: #fed7d7;
      color: #e53e3e;
      cursor: not-allowed;
    }
    
    .calendar-day.blocked {
      background: #cbd5e0;
      color: #718096;
      cursor: not-allowed;
    }
    
    .calendar-legend {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: var(--text-secondary, #666);
    }
    
    .legend-color {
      width: 20px;
      height: 20px;
      border-radius: 4px;
    }
    
    .legend-color.available {
      background: #c6f6d5;
    }
    
    .legend-color.booked {
      background: #fed7d7;
    }
    
    .legend-color.blocked {
      background: #cbd5e0;
    }
  `;
  document.head.appendChild(style);
}

// Export functions
window.AvailabilityCalendar = {
  createAvailabilityCalendar,
  loadAvailability,
  getBlockedDates,
  getBookingsForRoom
};

