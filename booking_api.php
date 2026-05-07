<?php
/**
 * Booking API Handler
 * Processing all API requests related to bookings
 */

// Disable error output for the API (so as not to break JSON responses)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'common.php';
require_once 'payment_service.php';
require_once 'email_service.php';
require_once 'airbnb_sync.php';
require_once 'jwt_helper.php';

// Getting an action from a request
$action = getApiAction();

// Action routing
switch ($action) {
    case 'check_availability':
        handleCheckAvailability();
        break;
    
    case 'create_booking':
        handleCreateBooking();
        break;
    
    case 'get_booking':
        handleGetBooking();
        break;
    
    case 'confirm_booking':
        handleConfirmBooking();
        break;
    
    case 'cancel_booking':
        handleCancelBooking();
        break;
    
    case 'delete_booking':
        handleDeleteBooking();
        break;
    
    case 'get_bookings':
        handleGetBookings();
        break;
    
    case 'block_date':
        handleBlockDate();
        break;
    
    case 'unblock_date':
        handleUnblockDate();
        break;
    
    case 'get_blocked_dates':
        handleGetBlockedDates();
        break;
    
    case 'sync_airbnb':
        handleSyncAirbnb();
        break;
    
    case 'get_airbnb_sync_status':
        handleGetAirbnbSyncStatus();
        break;
    
    case 'get_massage_bookings':
        handleGetMassageBookings();
        break;
    
    case 'create_massage_booking':
        handleCreateMassageBooking();
        break;
    
    case 'confirm_massage_booking':
        handleConfirmMassageBooking();
        break;
    
    case 'cancel_massage_booking':
        handleCancelMassageBooking();
        break;
    
    case 'delete_massage_booking':
        handleDeleteMassageBooking();
        break;
    
    case 'update_guest_room_booking':
        handleUpdateGuestRoomBooking();
        break;
    
    case 'update_guest_massage_booking':
        handleUpdateGuestMassageBooking();
        break;
    
    default:
        // If the action is not related to bookings, we do not process it
        break;
}

/**
 * Number of dogs for a room booking, stored in bookings.pets (0–2).
 * Legacy values: add / yes / true → 1, no → 0.
 */
function btb_normalize_room_booking_pets($raw): int {
    if ($raw === true) {
        return 1;
    }
    if ($raw === false || $raw === null) {
        return 0;
    }
    $s = strtolower(trim((string) $raw));
    if (in_array($s, ['add', 'yes', 'true'], true)) {
        return 1;
    }
    if ($s === 'no') {
        return 0;
    }
    $n = (int) $raw;
    if ($n < 0) {
        return 0;
    }
    if ($n > 2) {
        return 2;
    }
    return $n;
}

/**
 * Checking date availability for a room
 */
function handleCheckAvailability() {
    global $conn;
    
    try {
        $roomName = sanitizeInput($_POST['room_name'] ?? $_GET['room_name'] ?? '');
        $checkinDate = sanitizeInput($_POST['checkin_date'] ?? $_GET['checkin_date'] ?? '');
        $checkoutDate = sanitizeInput($_POST['checkout_date'] ?? $_GET['checkout_date'] ?? '');
        
        if (empty($roomName)) {
            sendError('Room name is required');
        }
        
        if (empty($checkinDate) || empty($checkoutDate)) {
            sendError('Check-in and check-out dates are required');
        }
        
        // Date Validation
        $checkin = DateTime::createFromFormat('Y-m-d', $checkinDate);
        $checkout = DateTime::createFromFormat('Y-m-d', $checkoutDate);
        
        if (!$checkin || !$checkout) {
            sendError('Invalid date format. Use YYYY-MM-DD');
        }
        
        if ($checkin >= $checkout) {
            sendError('Check-out date must be after check-in date');
        }
        
        if ($checkin < new DateTime('today')) {
            sendError('Check-in date cannot be in the past');
        }
        
        // Checking availability
        // FIXED: Only confirmed bookings are taken into account
        // Pending bookings DO NOT block dates so as not to disturb other clients
        $excludeBookingId = intval($_POST['exclude_booking_id'] ?? $_GET['exclude_booking_id'] ?? 0);
        error_log("Check availability: Room={$roomName}, Check-in={$checkinDate}, Check-out={$checkoutDate}");
        $isAvailable = checkDateAvailability($conn, $roomName, $checkinDate, $checkoutDate, $excludeBookingId > 0 ? $excludeBookingId : null);
        error_log("Check availability result: " . ($isAvailable ? 'available' : 'not available (conflict with confirmed booking)'));
        
        if ($isAvailable) {
            sendSuccess(['available' => true, 'message' => 'Dates are available']);
        } else {
            sendSuccess(['available' => false, 'message' => 'Dates are not available (conflict with confirmed booking)']);
        }
        
    } catch (Exception $e) {
        logActivity("Check availability error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to check availability: ' . $e->getMessage());
    }
}

/**
 * Create a new booking
 */
function handleCreateBooking() {
    global $conn;
    
    try {
        // Receiving data from the request
        $data = [
            'room_name' => sanitizeInput($_POST['room_name'] ?? ''),
            'checkin_date' => sanitizeInput($_POST['checkin_date'] ?? ''),
            'checkout_date' => sanitizeInput($_POST['checkout_date'] ?? ''),
            'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'guests_count' => intval($_POST['guests_count'] ?? 1),
            'pets' => btb_normalize_room_booking_pets($_POST['pets'] ?? 0),
            'special_requests' => sanitizeInput($_POST['special_requests'] ?? '')
        ];
        
        // Validation of required fields
        $required = ['room_name', 'checkin_date', 'checkout_date', 'guest_name', 'email', 'phone'];
        $errors = validateRequired($data, $required);
        
        if (!empty($errors)) {
            sendError('Validation failed: ' . implode(', ', $errors));
        }
        
        // Email Validation
        if (!validateEmail($data['email'])) {
            sendError('Invalid email address');
        }
        
        // Phone validation
        if (!validatePhone($data['phone'])) {
            sendError('Invalid phone number');
        }
        
        // Date Validation
        $checkin = DateTime::createFromFormat('Y-m-d', $data['checkin_date']);
        $checkout = DateTime::createFromFormat('Y-m-d', $data['checkout_date']);
        
        if (!$checkin || !$checkout) {
            sendError('Invalid date format');
        }
        
        if ($checkin >= $checkout) {
            sendError('Check-out date must be after check-in date');
        }
        
        if ($checkin < new DateTime('today')) {
            sendError('Check-in date cannot be in the past');
        }
        
        // Checking availability of dates
        error_log("Create booking: Checking availability for room {$data['room_name']}, check-in: {$data['checkin_date']}, check-out: {$data['checkout_date']}");
        $isAvailable = checkDateAvailability($conn, $data['room_name'], $data['checkin_date'], $data['checkout_date'], null);
        error_log("Create booking: Availability check result: " . ($isAvailable ? 'available' : 'not available'));
        
        if (!$isAvailable) {
            error_log("Create booking: Dates are not available, aborting booking creation");
            sendError('Selected dates are not available');
        }
        
        error_log("Create booking: Dates are available, proceeding with booking creation");
        
        // Nightly rate: only CMS merge (same fields as public room price line), never silent 150/rooms for named rooms
        $roomPrice = getRoomPrice($conn, $data['room_name']);
        if ($roomPrice === null || $roomPrice <= 0) {
            sendError('This room has no nightly rate set in the admin (price amount). Please configure it and try again, or contact the property.');
        }
        $nights = $checkin->diff($checkout)->days;
        $totalAmount = $roomPrice * $nights;
        error_log("Create booking: Calculated price: {$roomPrice} CAD/night, {$nights} nights, total: {$totalAmount} CAD");
        
        // Preparing data for insertion
        $bookingData = [
            'room_name' => $data['room_name'],
            'checkin_date' => $data['checkin_date'],
            'checkout_date' => $data['checkout_date'],
            'guest_name' => $data['guest_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'guests_count' => $data['guests_count'],
            'pets' => $data['pets'],
            'total_amount' => $totalAmount,
            'currency' => 'CAD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'special_requests' => $data['special_requests']
        ];
        
        // Create a reservation
        error_log("Create booking: Attempting to insert booking record into database");
        $bookingId = insertRecord($conn, 'bookings', $bookingData);
        
        if (!$bookingId) {
            error_log("Create booking: ERROR - Failed to insert booking record into database");
            sendError('Failed to create booking');
        }
        
        error_log("Create booking: Booking record created successfully with ID: {$bookingId}");
        
        // Generating a confirmation code
        $confirmationCode = generateConfirmationCode($bookingId);
        error_log("Create booking: Generated confirmation code: {$confirmationCode}");
        
        // Create a confirmation record
        $confirmationData = [
            'booking_id' => $bookingId,
            'confirmation_code' => $confirmationCode
        ];
        $confirmationId = insertRecord($conn, 'booking_confirmations', $confirmationData);
        if ($confirmationId) {
            error_log("Create booking: Confirmation record created with ID: {$confirmationId}");
        } else {
            error_log("Create booking: WARNING - Failed to create confirmation record, but continuing");
        }
        
        // We receive complete information about the reservation
        error_log("Create booking: Fetching full booking details for ID: {$bookingId}");
        $booking = getBookingById($conn, $bookingId);
        
        if (!$booking) {
            error_log("Create booking: ERROR - Failed to fetch booking details after creation");
            sendError('Failed to retrieve booking details');
        }
        
        error_log("Create booking: Booking details retrieved successfully. Email: " . ($booking['email'] ?? 'N/A'));
        
        // Create a Payment Intent for payment (if Stripe is configured)
        $paymentIntent = null;
        $clientSecret = null;
        
        if (!empty(STRIPE_SECRET_KEY) && $totalAmount > 0) {
            try {
                $paymentResult = createPaymentIntent($booking);
                if ($paymentResult['success']) {
                    $paymentIntent = $paymentResult['payment_intent_id'];
                    $clientSecret = $paymentResult['client_secret'];
                } else {
                    // We log the Payment Intent creation error, but do not interrupt the process
                    logActivity("Failed to create Payment Intent: " . ($paymentResult['error'] ?? 'Unknown error'), 'WARNING');
                }
            } catch (Exception $e) {
                // We log the error, but do not interrupt the booking creation process
                logActivity("Payment Intent creation error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // Send email confirmation to the guest (if the email service is configured)
        if (!empty(MAILGUN_API_KEY)) {
            error_log("Booking API: Attempting to send confirmation email to guest: " . ($booking['email'] ?? 'N/A'));
            try {
                $emailResult = sendBookingConfirmation($booking);
                error_log("Booking API: Guest confirmation email result: " . json_encode($emailResult));
                if (!$emailResult || !$emailResult['success']) {
                    error_log("Booking API: Guest confirmation email failed: " . ($emailResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // We log the email error, but do not interrupt the booking process
                error_log("Booking API: Exception sending guest confirmation email: " . $e->getMessage());
                logActivity("Failed to send booking confirmation email: " . $e->getMessage(), 'WARNING');
            }
        } else {
            error_log("Booking API: MAILGUN_API_KEY is empty, skipping email sending");
        }
        
        // We send a notification to the owner about a new booking (if the email service is configured)
        if (!empty(MAILGUN_API_KEY)) {
            error_log("Booking API: Attempting to send notification email to host: " . (defined('MAILGUN_HOST_EMAIL') ? MAILGUN_HOST_EMAIL : 'N/A'));
            try {
                $hostEmailResult = sendBookingRequestToHost($booking);
                error_log("Booking API: Host notification email result: " . json_encode($hostEmailResult));
                if (!$hostEmailResult || !$hostEmailResult['success']) {
                    error_log("Booking API: Host notification email failed: " . ($hostEmailResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // We log the email error, but do not interrupt the process
                error_log("Booking API: Exception sending host notification email: " . $e->getMessage());
                logActivity("Failed to send booking request to host: " . $e->getMessage(), 'WARNING');
            }
        } else {
            error_log("Booking API: MAILGUN_API_KEY is empty, skipping host email");
        }
        
        // Logging the creation of a reservation
        logActivity("Booking created: ID {$bookingId}, Room: {$data['room_name']}, Guest: {$data['guest_name']}");
        
        $response = [
            'booking_id' => $bookingId,
            'confirmation_code' => $confirmationCode,
            'booking' => $booking,
            'nightly_rate' => (float) $roomPrice,
            'message' => 'Booking created successfully'
        ];
        
        // Add payment details to the response
        if ($paymentIntent) {
            $response['payment_intent_id'] = $paymentIntent;
            $response['client_secret'] = $clientSecret;
            $response['payment_required'] = true;
        }
        
        sendSuccess($response);
        
    } catch (Exception $e) {
        logActivity("Create booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to create booking: ' . $e->getMessage());
    }
}

/**
 * Receiving a reservation by ID
 */
function handleGetBooking() {
    global $conn;
    
    try {
        $bookingId = intval($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
        $confirmationCode = sanitizeInput($_GET['confirmation_code'] ?? $_POST['confirmation_code'] ?? '');
        
        if ($bookingId > 0) {
            $booking = getBookingById($conn, $bookingId);
            if (!$booking) {
                sendError('Booking not found');
            }
            sendSuccess(['booking' => $booking]);
        } elseif (!empty($confirmationCode)) {
            $booking = getBookingByConfirmationCode($conn, $confirmationCode);
            if (!$booking) {
                sendError('Booking not found');
            }
            sendSuccess(['booking' => $booking]);
        } else {
            sendError('Booking ID or confirmation code is required');
        }
        
    } catch (Exception $e) {
        logActivity("Get booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to get booking: ' . $e->getMessage());
    }
}

/**
 * Booking confirmation by administrator
 */
function handleConfirmBooking() {
    global $conn;
    
    try {
        // Checking administrator authorization
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // We receive a reservation
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        if ($booking['status'] !== 'pending') {
            sendError('Booking cannot be confirmed. Current status: ' . $booking['status']);
        }
        
        // Update the status
        $updateData = [
            'status' => 'confirmed',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to confirm booking');
        }
        
        // Updating host confirmation time
        updateRecord($conn, 'booking_confirmations', 
            ['host_confirmed_at' => date('Y-m-d H:i:s')], 
            'booking_id = ?', 
            [$bookingId]
        );
        
        // Logging
        logActivity("Booking confirmed: ID {$bookingId}");
        
        // We receive an updated reservation
        $updatedBooking = getBookingById($conn, $bookingId);
        
        // We send email confirmation to the guest after approval by the host
        if (!empty(MAILGUN_API_KEY)) {
            try {
                sendBookingConfirmedToGuest($updatedBooking);
            } catch (Exception $e) {
                // We log the email error, but do not interrupt the process
                logActivity("Failed to send booking confirmed email: " . $e->getMessage(), 'WARNING');
            }
        }
        
        sendSuccess([
            'booking' => $updatedBooking,
            'message' => 'Booking confirmed successfully'
        ]);
        
    } catch (Exception $e) {
        logActivity("Confirm booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to confirm booking: ' . $e->getMessage());
    }
}

/**
 * Cancellation
 */
function handleCancelBooking() {
    global $conn;
    
    try {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // We receive a reservation
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        if ($booking['status'] === 'cancelled') {
            sendError('Booking is already cancelled');
        }
        
        // Update the status
        $updateData = [
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to cancel booking');
        }
        
        // If there was a payment, we process the refund through Stripe
        if ($booking['payment_status'] === 'paid' && !empty(STRIPE_SECRET_KEY)) {
            try {
                $refundResult = refundPayment($bookingId);
                if (!$refundResult['success']) {
                    // Logging a refund error, but continuing to cancel the reservation
                    logActivity("Failed to refund payment: " . ($refundResult['error'] ?? 'Unknown error'), 'WARNING');
                } else {
                    logActivity("Payment refunded successfully: Booking {$bookingId}, Amount: {$refundResult['amount']}");
                }
            } catch (Exception $e) {
                // We log the error, but do not interrupt the cancellation process
                logActivity("Refund error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        logActivity("Booking cancelled: ID {$bookingId}, Reason: {$reason}");
        
        $updatedBooking = getBookingById($conn, $bookingId);
        
        // We send an email about booking cancellation to the guest
        if (!empty(MAILGUN_API_KEY)) {
            try {
                sendBookingCancelled($updatedBooking, $reason);
            } catch (Exception $e) {
                // We log the email error, but do not interrupt the process
                logActivity("Failed to send booking cancelled email: " . $e->getMessage(), 'WARNING');
            }
        }
        
        sendSuccess([
            'booking' => $updatedBooking,
            'message' => 'Booking cancelled successfully'
        ]);
        
    } catch (Exception $e) {
        logActivity("Cancel booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to cancel booking: ' . $e->getMessage());
    }
}

/**
 * Deleting a booking (complete deletion from the database)
 */
function handleDeleteBooking() {
    global $conn;
    
    try {
        // Checking administrator authorization
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // Receiving a reservation before deleting it for logging
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        // We delete related records (booking_confirmations will be deleted automatically due to CASCADE)
        // But it’s better to delete it explicitly for safety
        deleteRecord($conn, 'booking_confirmations', 'booking_id = ?', [$bookingId]);
        
        // We delete the reservation itself
        $result = deleteRecord($conn, 'bookings', 'id = ?', [$bookingId]);
        
        // We check that the deletion was successful (affected_rows > 0)
        if ($result === false || $result <= 0) {
            sendError('Failed to delete booking from database');
        }
        
        logActivity("Booking deleted permanently: ID {$bookingId}, Room: {$booking['room_name']}, Guest: {$booking['guest_name']}, Email: {$booking['email']}");
        
        sendSuccess([
            'message' => 'Booking deleted successfully',
            'booking_id' => $bookingId,
            'email' => $booking['email'] ?? ''
        ]);
        
    } catch (Exception $e) {
        logActivity("Delete booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to delete booking: ' . $e->getMessage());
    }
}

/**
 * Getting a list of all bookings (for the admin panel)
 */
function handleGetBookings() {
    global $conn;
    
    try {
        // Checking administrator authorization (full list only)
        // For the public availability calendar, no login is required, only a filter by room
        $roomName = sanitizeInput($_GET['room_name'] ?? $_POST['room_name'] ?? '');
        $isPublicRequest = !empty($roomName); // Public request - only for a specific room
        
        if (!$isPublicRequest && !isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        // Filters
        $status = sanitizeInput($_GET['status'] ?? '');
        // $roomName is already defined above for authorization check
        $dateFrom = sanitizeInput($_GET['date_from'] ?? '');
        $dateTo = sanitizeInput($_GET['date_to'] ?? '');
        
        $where = [];
        $params = [];
        
        if (!empty($status)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        
        if (!empty($roomName)) {
            $where[] = 'room_name = ?';
            $params[] = $roomName;
        }
        
        if (!empty($dateFrom)) {
            $where[] = 'checkout_date >= ?';
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $where[] = 'checkin_date <= ?';
            $params[] = $dateTo;
        }
        
        $whereClause = !empty($where) ? implode(' AND ', $where) : '1=1';
        
        $sql = "SELECT * FROM bookings WHERE {$whereClause} ORDER BY created_at DESC";
        $bookings = fetchAll($conn, $sql, $params);
        
        sendSuccess(['bookings' => $bookings, 'count' => count($bookings)]);
        
    } catch (Exception $e) {
        logActivity("Get bookings error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to get bookings: ' . $e->getMessage());
    }
}

/**
 * Manually blocking the date
 */
function handleBlockDate() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $roomName = sanitizeInput($_POST['room_name'] ?? '');
        $dateFrom = sanitizeInput($_POST['date_from'] ?? '');
        $dateTo = sanitizeInput($_POST['date_to'] ?? '');
        $reason = sanitizeInput($_POST['reason'] ?? 'Manually blocked by admin');
        
        // Support for old format (blocked_date) for backwards compatibility
        if (empty($dateFrom) && !empty($_POST['blocked_date'] ?? '')) {
            $dateFrom = sanitizeInput($_POST['blocked_date']);
            $dateTo = $dateFrom;
        }
        
        if (empty($roomName) || empty($dateFrom) || empty($dateTo)) {
            sendError('Room name, date from, and date to are required');
        }
        
        // Checking the validity of dates
        $dateFromObj = DateTime::createFromFormat('Y-m-d', $dateFrom);
        $dateToObj = DateTime::createFromFormat('Y-m-d', $dateTo);
        
        if (!$dateFromObj || !$dateToObj) {
            sendError('Invalid date format');
        }
        
        if ($dateFromObj > $dateToObj) {
            sendError('Date from must be before or equal to date to');
        }
        
        // Checking whether the period overlaps with existing locks
        // If room_name = "__all__", check intersections for all rooms and massage
        // Safe SQL: first try with date_from/date_to, if it doesn’t work, use blocked_date
        $existing = [];
        try {
            // Trying a request with date_from/date_to (new format)
            if ($roomName === '__all__') {
                // For "__all__" we check intersections with any locks (including other "__all__")
                $existing = fetchAll($conn, 
                    "SELECT * FROM blocked_dates 
                     WHERE (
                         (date_from <= ? AND date_to >= ?) OR
                         (date_from <= ? AND date_to >= ?) OR
                         (date_from >= ? AND date_to <= ?)
                     )",
                    [$dateFrom, $dateFrom, $dateTo, $dateTo, $dateFrom, $dateTo]
                );
            } else {
                // For a specific room, we check intersections with the locks of this room or "__all__"
                $existing = fetchAll($conn, 
                    "SELECT * FROM blocked_dates 
                     WHERE (room_name = ? OR room_name = '__all__')
                     AND (
                         (date_from <= ? AND date_to >= ?) OR
                         (date_from <= ? AND date_to >= ?) OR
                         (date_from >= ? AND date_to <= ?)
                     )",
                    [$roomName, $dateFrom, $dateFrom, $dateTo, $dateTo, $dateFrom, $dateTo]
                );
            }
        } catch (Exception $e) {
            // If the request fails (the fields do not exist), use the old format
            try {
                if ($roomName === '__all__') {
                    $existing = fetchAll($conn, 
                        "SELECT * FROM blocked_dates 
                         WHERE blocked_date >= ? 
                         AND blocked_date <= ?",
                        [$dateFrom, $dateTo]
                    );
                } else {
                    $existing = fetchAll($conn, 
                        "SELECT * FROM blocked_dates 
                         WHERE (room_name = ? OR room_name = '__all__')
                         AND blocked_date >= ? 
                         AND blocked_date <= ?",
                        [$roomName, $dateFrom, $dateTo]
                    );
                }
            } catch (Exception $e2) {
                // If this does not work, we assume that there are no intersections
                logActivity("Check blocked dates overlap error: " . $e2->getMessage(), 'ERROR');
                $existing = [];
            }
        }
        
        if (!empty($existing)) {
            sendError('This date range overlaps with an existing blocked period');
        }
        
        // Create a period lock
        $blockData = [
            'room_name' => $roomName,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'reason' => $reason
        ];
        
        // If there is an old blocked_date field, we use it for backward compatibility
        if (isset($_POST['blocked_date']) && !isset($blockData['blocked_date'])) {
            $blockData['blocked_date'] = $dateFrom; // For backward compatibility
        }
        
        $blockId = insertRecord($conn, 'blocked_dates', $blockData);
        
        if (!$blockId) {
            sendError('Failed to block date period');
        }
        
        logActivity("Date period blocked: Room {$roomName}, From {$dateFrom} to {$dateTo}, Reason: {$reason}");
        
        sendSuccess(['block_id' => $blockId, 'message' => 'Date period blocked successfully']);
        
    } catch (Exception $e) {
        logActivity("Block date error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to block date period: ' . $e->getMessage());
    }
}

/**
 * Unlock date
 */
function handleUnblockDate() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        // Support for old format (blocked_date_id) for backward compatibility
        $blockId = intval($_POST['block_id'] ?? $_POST['blocked_date_id'] ?? 0);
        
        if ($blockId <= 0) {
            sendError('Invalid block ID');
        }
        
        // Getting information about blocking before deleting
        $blocked = fetchOne($conn, "SELECT * FROM blocked_dates WHERE id = ?", [$blockId]);
        
        if (!$blocked) {
            sendError('Blocked period not found');
        }
        
        $result = deleteRecord($conn, 'blocked_dates', 'id = ?', [$blockId]);
        
        if (!$result) {
            sendError('Failed to unblock date period');
        }
        
        $dateFrom = $blocked['date_from'] ?? $blocked['blocked_date'] ?? 'unknown';
        $dateTo = $blocked['date_to'] ?? $blocked['blocked_date'] ?? 'unknown';
        logActivity("Date period unblocked: Block ID {$blockId}, Room: {$blocked['room_name']}, From {$dateFrom} to {$dateTo}");
        
        sendSuccess(['message' => 'Date period unblocked successfully']);
        
    } catch (Exception $e) {
        logActivity("Unblock date error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to unblock date period: ' . $e->getMessage());
    }
}

/**
 * Retrieving blocked dates
 */
function handleGetBlockedDates() {
    global $conn;
    
    try {
        $roomName = sanitizeInput($_GET['room_name'] ?? $_POST['room_name'] ?? '');
        
        // Getting manual locks
        // If a specific room is requested, return the locks for that room AND the "__all__" locks (for everyone)
        // If room_name is not specified, return all locks
        $where = [];
        $params = [];
        
        if (!empty($roomName)) {
            // For a specific room, we show the locks of this room and "__all__"
            $where[] = '(room_name = ? OR room_name = \'__all__\')';
            $params[] = $roomName;
        }
        
        $whereClause = !empty($where) ? implode(' AND ', $where) : '1=1';
        
        // Safe SQL query: first try with date_from/date_to, if it doesn’t work, use blocked_date
        // Checking if the date_from and date_to fields exist in the table
        $blockedDates = [];
        try {
            // Trying a request with date_from/date_to (new format)
            $orderBy = "COALESCE(date_from, blocked_date) ASC";
            $sql = "SELECT * FROM blocked_dates WHERE {$whereClause} ORDER BY {$orderBy}";
            $result = fetchAll($conn, $sql, $params);
            // fetchAll can return false on error, check this
            if ($result !== false) {
                $blockedDates = $result;
            }
        } catch (Exception $e) {
            // If the request fails (the fields do not exist), use the old format
            try {
                $sql = "SELECT * FROM blocked_dates WHERE {$whereClause} ORDER BY blocked_date ASC";
                $result = fetchAll($conn, $sql, $params);
                if ($result !== false) {
                    $blockedDates = $result;
                }
            } catch (Exception $e2) {
                // If this doesn't work, return an empty array
                logActivity("Get blocked dates SQL error: " . $e2->getMessage(), 'ERROR');
                $blockedDates = [];
            }
        }
        
        // For backward compatibility: if there is a blocked_date but no date_from/date_to, create a period
        if (is_array($blockedDates)) {
            foreach ($blockedDates as &$blocked) {
                if (empty($blocked['date_from']) && !empty($blocked['blocked_date'])) {
                    $blocked['date_from'] = $blocked['blocked_date'];
                    $blocked['date_to'] = $blocked['blocked_date'];
                }
            }
            unset($blocked);
        }
        
        // We also receive Airbnb blocked dates
        $airbnbBlockedDates = [];
        if (!empty($roomName)) {
            $airbnbBlockedDates = getAirbnbBlockedDates($conn, $roomName);
        }
        
        sendSuccess([
            'blocked_dates' => $blockedDates,
            'airbnb_blocked_dates' => $airbnbBlockedDates
        ]);
        
    } catch (Exception $e) {
        logActivity("Get blocked dates error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to get blocked dates: ' . $e->getMessage());
    }
}

/**
 * Airbnb Calendar Sync
 */
function handleSyncAirbnb() {
    global $conn;
    
    try {
        // Checking administrator authorization
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $roomName = sanitizeInput($_POST['room_name'] ?? $_GET['room_name'] ?? '');
        $roomName = !empty($roomName) ? $roomName : null;
        
        // Performing synchronization
        $result = syncAirbnbCalendar($roomName);
        
        if ($result['success']) {
            sendSuccess([
                'message' => 'Airbnb calendar synced successfully',
                'synced_rooms' => $result['synced_rooms'],
                'errors' => $result['errors']
            ]);
        } else {
            sendError('Airbnb sync failed: ' . json_encode($result['errors']));
        }
        
    } catch (Exception $e) {
        logActivity("Sync Airbnb error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to sync Airbnb calendar: ' . $e->getMessage());
    }
}

/**
 * Getting Airbnb Sync Status
 */
function handleGetAirbnbSyncStatus() {
    global $conn;
    
    try {
        // Administrator authorization check (for full status)
        $roomName = sanitizeInput($_GET['room_name'] ?? '');
        $roomName = !empty($roomName) ? $roomName : null;
        
        // No authorization required for public access (specific room)
        if (!$roomName && !isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $status = getAirbnbSyncStatus($conn, $roomName);
        
        sendSuccess(['sync_status' => $status]);
        
    } catch (Exception $e) {
        logActivity("Get Airbnb sync status error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to get Airbnb sync status: ' . $e->getMessage());
    }
}

/**
 * Receiving massage bookings
 */
function handleGetMassageBookings() {
    global $conn;
    
    try {
        // Checking if the massage_bookings table exists
        $tableExists = false;
        try {
            $result = $conn->query("SHOW TABLES LIKE 'massage_bookings'");
            $tableExists = $result && $result->num_rows > 0;
        } catch (Exception $e) {
            // Table does not exist
            $tableExists = false;
        }
        
        if (!$tableExists) {
            // If the table does not exist, return an empty array
            sendSuccess(['bookings' => []]);
            return;
        }
        
        // Getting filtering parameters
        $status = sanitizeInput($_GET['status'] ?? $_POST['status'] ?? '');
        $dateFrom = sanitizeInput($_GET['date_from'] ?? $_POST['date_from'] ?? '');
        $dateTo = sanitizeInput($_GET['date_to'] ?? $_POST['date_to'] ?? '');
        
        // Building an SQL query with filters
        $where = [];
        $params = [];
        
        // Filter by status
        if (!empty($status)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        
        // Filter by date (date_from)
        if (!empty($dateFrom)) {
            $where[] = 'massage_date >= ?';
            $params[] = $dateFrom;
        }
        
        // Filter by date (date_to)
        if (!empty($dateTo)) {
            $where[] = 'massage_date <= ?';
            $params[] = $dateTo;
        }
        
        // Forming a WHERE clause
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // We receive all massage bookings (including pending, if the status filter is not specified)
        $sql = "SELECT * FROM massage_bookings 
                {$whereClause}
                ORDER BY created_at DESC, massage_date ASC, massage_time ASC";
        
        $bookings = fetchAll($conn, $sql, $params);
        
        if ($bookings === false) {
            $bookings = [];
        }
        
        sendSuccess(['bookings' => $bookings]);
        
    } catch (Exception $e) {
        logActivity("Get massage bookings error: " . $e->getMessage(), 'ERROR');
        sendSuccess(['bookings' => []]); // Return an empty array instead of an error
    }
}

/**
 * Making a massage reservation
 */
function handleCreateMassageBooking() {
    global $conn;
    
    try {
        // Receiving data from the request
        $data = [
            'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'massage_date' => sanitizeInput($_POST['massage_date'] ?? ''),
            'massage_time' => sanitizeInput($_POST['massage_time'] ?? ''),
            'massage_type' => sanitizeInput($_POST['massage_type'] ?? ''),
            'duration' => intval($_POST['duration'] ?? 60),
            'with_room' => sanitizeInput($_POST['with_room'] ?? ''),
            'status' => 'pending'
        ];
        
        // Validation of required fields
        if (empty($data['guest_name']) || empty($data['email']) || empty($data['phone']) || 
            empty($data['massage_date']) || empty($data['massage_time'])) {
            sendError('All required fields must be filled');
        }
        
        // Email Validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            sendError('Invalid email address');
        }
        
        // Date Validation
        $massageDate = DateTime::createFromFormat('Y-m-d', $data['massage_date']);
        if (!$massageDate || $massageDate->format('Y-m-d') !== $data['massage_date']) {
            sendError('Invalid massage date format');
        }
        
        // Checking that the date is not in the past
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $massageDate->setTime(0, 0, 0);
        if ($massageDate < $today) {
            sendError('Massage date cannot be in the past');
        }
        
        // Checking if the massage_bookings table exists
        $tableExists = false;
        try {
            $result = $conn->query("SHOW TABLES LIKE 'massage_bookings'");
            $tableExists = $result && $result->num_rows > 0;
        } catch (Exception $e) {
            $tableExists = false;
        }
        
        if (!$tableExists) {
            sendError('Massage bookings table does not exist');
        }
        
        $linePrice = getMassageLinePrice($conn, $data['massage_type'], (int) $data['duration']);
        $mbCols = btb_massage_bookings_column_set($conn);

        // Create a reservation
        $bookingData = [
            'guest_name' => $data['guest_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'massage_date' => $data['massage_date'],
            'massage_time' => $data['massage_time'],
            'massage_type' => $data['massage_type'],
            'duration' => $data['duration'],
            'status' => $data['status'],
            'notes' => $data['with_room'] ? 'Staying with us: ' . $data['with_room'] : ''
        ];
        if (!empty($mbCols['total_amount'])) {
            $bookingData['total_amount'] = $linePrice;
        }
        if (!empty($mbCols['currency'])) {
            $bookingData['currency'] = 'CAD';
        }

        $bookingId = insertRecord($conn, 'massage_bookings', $bookingData);
        
        if (!$bookingId) {
            sendError('Failed to create massage booking');
        }
        
        // Same human-friendly code format as room bookings (NNN-NNN)
        $confirmationCode = btb_allocate_unique_guest_confirmation_code($conn);
        
        // Updating the verification code
        updateRecord($conn, 'massage_bookings', ['confirmation_code' => $confirmationCode], 'id = ?', [$bookingId]);
        
        logActivity("Massage booking created: ID {$bookingId}, Guest: {$data['guest_name']}, Date: {$data['massage_date']}, Time: {$data['massage_time']}");
        
        $massageRow = array_merge($bookingData, [
            'id' => $bookingId,
            'confirmation_code' => $confirmationCode
        ]);
        
        if (!empty(MAILGUN_API_KEY)) {
            error_log('Massage booking API: sending guest confirmation to ' . ($massageRow['email'] ?? ''));
            try {
                $guestMail = sendMassageBookingConfirmationToGuest($massageRow);
                error_log('Massage booking API: guest email result: ' . json_encode($guestMail));
            } catch (Exception $e) {
                error_log('Massage booking API: guest email exception: ' . $e->getMessage());
                logActivity('Failed to send massage guest email: ' . $e->getMessage(), 'WARNING');
            }
            try {
                $hostMail = sendMassageBookingRequestToHost($massageRow);
                error_log('Massage booking API: host email result: ' . json_encode($hostMail));
            } catch (Exception $e) {
                error_log('Massage booking API: host email exception: ' . $e->getMessage());
                logActivity('Failed to send massage host email: ' . $e->getMessage(), 'WARNING');
            }
        } else {
            error_log('Massage booking API: MAILGUN_API_KEY empty, skipping emails');
        }
        
        $respCreate = [
            'booking_id' => $bookingId,
            'confirmation_code' => $confirmationCode,
            'message' => 'Massage booking created successfully',
        ];
        if (!empty($mbCols['total_amount'])) {
            $respCreate['total_amount'] = (float) $linePrice;
        }
        if (!empty($mbCols['currency'])) {
            $respCreate['currency'] = 'CAD';
        }
        sendSuccess($respCreate);
        
    } catch (Exception $e) {
        logActivity("Create massage booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to create massage booking: ' . $e->getMessage());
    }
}

/**
 * Massage booking confirmation
 */
function handleConfirmMassageBooking() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        $before = getMassageBookingById($conn, $bookingId);
        if (!$before) {
            sendError('Booking not found');
        }
        
        // Update the status
        $result = updateRecord($conn, 'massage_bookings', ['status' => 'confirmed'], 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to confirm massage booking');
        }
        
        logActivity("Massage booking confirmed: ID {$bookingId}");
        $updated = getMassageBookingById($conn, $bookingId);
        if (!empty(MAILGUN_API_KEY) && $updated && function_exists('sendMassageBookingConfirmedToGuest')) {
            try {
                sendMassageBookingConfirmedToGuest($updated);
            } catch (Exception $e) {
                logActivity('Failed to send massage confirmed email: ' . $e->getMessage(), 'WARNING');
            }
        }
        
        sendSuccess(['message' => 'Massage booking confirmed successfully']);
        
    } catch (Exception $e) {
        logActivity("Confirm massage booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to confirm massage booking: ' . $e->getMessage());
    }
}

/**
 * Cancellation of massage booking
 */
function handleCancelMassageBooking() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        $before = getMassageBookingById($conn, $bookingId);
        if (!$before) {
            sendError('Booking not found');
        }
        
        // Update the status
        $updateData = ['status' => 'cancelled'];
        if ($reason) {
            $existingNotes = trim((string) ($before['notes'] ?? ''));
            $prefix = $existingNotes !== '' ? ($existingNotes . ' ') : '';
            $updateData['notes'] = $prefix . 'Cancellation reason: ' . $reason;
        }
        
        $result = updateRecord($conn, 'massage_bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to cancel massage booking');
        }
        
        logActivity("Massage booking cancelled: ID {$bookingId}, Reason: {$reason}");
        $updated = getMassageBookingById($conn, $bookingId);
        if (!empty(MAILGUN_API_KEY) && $updated && function_exists('sendMassageBookingCancelledToGuest')) {
            try {
                sendMassageBookingCancelledToGuest($updated, $reason);
            } catch (Exception $e) {
                logActivity('Failed to send massage cancelled email: ' . $e->getMessage(), 'WARNING');
            }
        }
        
        sendSuccess(['message' => 'Massage booking cancelled successfully']);
        
    } catch (Exception $e) {
        logActivity("Cancel massage booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to cancel massage booking: ' . $e->getMessage());
    }
}

/**
 * Deleting a massage booking
 */
function handleDeleteMassageBooking() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // Deleting a reservation
        $result = deleteRecord($conn, 'massage_bookings', 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to delete massage booking');
        }
        
        logActivity("Massage booking deleted: ID {$bookingId}");
        
        sendSuccess(['message' => 'Massage booking deleted successfully']);
        
    } catch (Exception $e) {
        logActivity("Delete massage booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to delete massage booking: ' . $e->getMessage());
    }
}

/**
 * The guest can edit the room reservation: confirmation code or login using the same email as in the reservation.
 */
function guestMayEditRoomBooking($conn, $booking, $confirmationCodePosted) {
    if (function_exists('btbJwtIsAdmin') && btbJwtIsAdmin()) {
        return true;
    }
    $posted = trim((string)($confirmationCodePosted ?? ''));
    $stored = trim((string)($booking['confirmation_code'] ?? ''));
    if ($posted !== '' && $stored !== '' && btb_confirmation_codes_match($posted, $stored)) {
        return true;
    }
    $user = authenticateUser($conn);
    if ($user && strtolower(trim((string)($user['email'] ?? ''))) === strtolower(trim((string)($booking['email'] ?? '')))) {
        return true;
    }
    return false;
}

/**
 * Same for wellness (massage_bookings).
 */
function guestMayEditMassageBooking($conn, $row, $confirmationCodePosted) {
    if (function_exists('btbJwtIsAdmin') && btbJwtIsAdmin()) {
        return true;
    }
    $posted = trim((string)($confirmationCodePosted ?? ''));
    $stored = trim((string)($row['confirmation_code'] ?? ''));
    if ($posted !== '' && $stored !== '' && btb_confirmation_codes_match($posted, $stored)) {
        return true;
    }
    $user = authenticateUser($conn);
    if ($user && strtolower(trim((string)($user['email'] ?? ''))) === strtolower(trim((string)($row['email'] ?? '')))) {
        return true;
    }
    return false;
}

function getMassageBookingById($conn, $id) {
    return fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ?', [intval($id)]);
}

/**
 * Updating a room reservation from “My Bookings” (rewriting a line in the database + letters).
 */
function handleUpdateGuestRoomBooking() {
    global $conn;
    
    try {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $confirmationCode = sanitizeInput($_POST['confirmation_code'] ?? '');
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        if (!guestMayEditRoomBooking($conn, $booking, $confirmationCode)) {
            sendError('Not authorized to update this booking');
        }
        
        $status = $booking['status'] ?? '';
        if ($status === 'cancelled') {
            sendError('This booking cannot be edited');
        }
        if (($booking['payment_status'] ?? '') === 'paid') {
            sendError('Paid bookings cannot be edited here. Please contact the property.');
        }
        
        $petsVal = btb_normalize_room_booking_pets($_POST['pets'] ?? '0');
        
        $data = [
            'room_name' => sanitizeInput($_POST['room_name'] ?? ''),
            'checkin_date' => sanitizeInput($_POST['checkin_date'] ?? ''),
            'checkout_date' => sanitizeInput($_POST['checkout_date'] ?? ''),
            'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'guests_count' => max(1, intval($_POST['guests_count'] ?? 1)),
            'pets' => $petsVal,
            'special_requests' => sanitizeInput($_POST['special_requests'] ?? '')
        ];
        
        $required = ['room_name', 'checkin_date', 'checkout_date', 'guest_name', 'email', 'phone'];
        $errors = validateRequired($data, $required);
        if (!empty($errors)) {
            sendError('Validation failed: ' . implode(', ', $errors));
        }
        if (!validateEmail($data['email'])) {
            sendError('Invalid email address');
        }
        if (!validatePhone($data['phone'])) {
            sendError('Invalid phone number');
        }
        
        $checkin = DateTime::createFromFormat('Y-m-d', $data['checkin_date']);
        $checkout = DateTime::createFromFormat('Y-m-d', $data['checkout_date']);
        if (!$checkin || !$checkout || $checkin->format('Y-m-d') !== $data['checkin_date'] || $checkout->format('Y-m-d') !== $data['checkout_date']) {
            sendError('Invalid date format');
        }
        if ($checkin >= $checkout) {
            sendError('Check-out date must be after check-in date');
        }
        if ($checkin < new DateTime('today')) {
            sendError('Check-in date cannot be in the past');
        }
        
        $isAvailable = checkDateAvailability($conn, $data['room_name'], $data['checkin_date'], $data['checkout_date'], $bookingId);
        if (!$isAvailable) {
            sendError('Selected dates are not available for this room');
        }
        
        $roomPrice = getRoomPrice($conn, $data['room_name']);
        if ($roomPrice === null || $roomPrice <= 0) {
            sendError('This room has no nightly rate set in the admin (price amount). Please configure it or contact the property.');
        }
        $nights = $checkin->diff($checkout)->days;
        $totalAmount = $roomPrice * $nights;
        
        $beforeSnapshot = $booking;
        
        $updateData = array_merge($data, [
            'total_amount' => $totalAmount,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        if ($result === false) {
            sendError('Failed to update booking');
        }
        
        $updated = getBookingById($conn, $bookingId);
        logActivity("Guest updated room booking ID {$bookingId}");

        // Do not block the HTTP JSON response on Mailgun latency (guest "Save" would stick on "Saving…").
        if (!empty(MAILGUN_API_KEY)) {
            register_shutdown_function(static function () use ($beforeSnapshot, $updated) {
                try {
                    sendRoomBookingUpdatedToGuestAndHost($beforeSnapshot, $updated);
                } catch (Throwable $e) {
                    logActivity('Guest room booking update emails: ' . $e->getMessage(), 'WARNING');
                }
            });
        }

        sendSuccess([
            'booking' => $updated,
            'nightly_rate' => (float) $roomPrice,
            'message' => 'Booking updated successfully'
        ]);
        
    } catch (Exception $e) {
        logActivity('Update guest room booking error: ' . $e->getMessage(), 'ERROR');
        sendError('Failed to update booking: ' . $e->getMessage());
    }
}

/**
 * Updating wellness reservation from “My Bookings”.
 */
function handleUpdateGuestMassageBooking() {
    global $conn;
    
    try {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $confirmationCode = sanitizeInput($_POST['confirmation_code'] ?? '');
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        $row = getMassageBookingById($conn, $bookingId);
        if (!$row) {
            sendError('Booking not found');
        }
        
        if (!guestMayEditMassageBooking($conn, $row, $confirmationCode)) {
            sendError('Not authorized to update this booking');
        }
        
        $status = $row['status'] ?? '';
        if ($status === 'cancelled') {
            sendError('This booking cannot be edited');
        }
        
        $data = [
            'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'massage_date' => sanitizeInput($_POST['massage_date'] ?? ''),
            'massage_time' => sanitizeInput($_POST['massage_time'] ?? ''),
            'massage_type' => sanitizeInput($_POST['massage_type'] ?? ''),
            'duration' => max(1, intval($_POST['duration'] ?? 60))
        ];
        
        if (empty($data['guest_name']) || empty($data['email']) || empty($data['phone']) ||
            empty($data['massage_date']) || empty($data['massage_time']) || empty($data['massage_type'])) {
            sendError('All required fields must be filled');
        }
        if (!validateEmail($data['email'])) {
            sendError('Invalid email address');
        }
        if (!validatePhone($data['phone'])) {
            sendError('Invalid phone number');
        }
        
        $massageDate = DateTime::createFromFormat('Y-m-d', $data['massage_date']);
        if (!$massageDate || $massageDate->format('Y-m-d') !== $data['massage_date']) {
            sendError('Invalid massage date format');
        }
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $massageDate->setTime(0, 0, 0);
        if ($massageDate < $today) {
            sendError('Massage date cannot be in the past');
        }
        
        $beforeSnapshot = $row;
        $linePrice = getMassageLinePrice($conn, $data['massage_type'], (int) $data['duration']);
        $mbCols = btb_massage_bookings_column_set($conn);
        $updateData = $data;
        if (!empty($mbCols['total_amount'])) {
            $updateData['total_amount'] = $linePrice;
        }
        if (!empty($mbCols['currency'])) {
            $updateData['currency'] = 'CAD';
        }

        $result = updateRecord($conn, 'massage_bookings', $updateData, 'id = ?', [$bookingId]);
        if ($result === false) {
            sendError('Failed to update booking');
        }
        
        $updated = getMassageBookingById($conn, $bookingId);
        logActivity("Guest updated massage booking ID {$bookingId}");

        if (!empty(MAILGUN_API_KEY)) {
            register_shutdown_function(static function () use ($beforeSnapshot, $updated) {
                try {
                    sendMassageBookingUpdatedToGuestAndHost($beforeSnapshot, $updated);
                } catch (Throwable $e) {
                    logActivity('Guest massage booking update emails: ' . $e->getMessage(), 'WARNING');
                }
            });
        }

        sendSuccess([
            'booking' => $updated,
            'message' => 'Booking updated successfully'
        ]);
        
    } catch (Exception $e) {
        logActivity('Update guest massage booking error: ' . $e->getMessage(), 'ERROR');
        sendError('Failed to update booking: ' . $e->getMessage());
    }
}

// ==========================================
// Auxiliary functions
// ==========================================

/**
 * Checking date availability for a room
 */
function checkDateAvailability($conn, $roomName, $checkinDate, $checkoutDate, $excludeBookingId = null) {
    try {
        // Checking existing bookings
        // FIXED: Only confirmed bookings are taken into account
        // Pending bookings DO NOT block dates so as not to disturb other clients
        $excludeId = ($excludeBookingId !== null && intval($excludeBookingId) > 0) ? intval($excludeBookingId) : 0;
        $sql = "SELECT COUNT(*) as count FROM bookings 
                WHERE room_name = ? 
                AND status IN ('confirmed', 'paid')
                AND (
                    (checkin_date <= ? AND checkout_date > ?) OR
                    (checkin_date < ? AND checkout_date >= ?) OR
                    (checkin_date >= ? AND checkout_date <= ?)
                )";
        $params = [
            $roomName,
            $checkinDate, $checkinDate,
            $checkoutDate, $checkoutDate,
            $checkinDate, $checkoutDate
        ];
        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = fetchOne($conn, $sql, $params);
        
        // If fetchOne returned false due to an error, we assume that the dates are available (so as not to block due to a database error)
        if ($result === false) {
            error_log("Check availability: WARNING - Database error when checking bookings, assuming available to avoid blocking");
            // We DO NOT return false in case of a database error, so as not to block the creation of a reservation.
        } else {
            $conflictingBookings = isset($result['count']) ? intval($result['count']) : 0;
            error_log("Check availability: Found {$conflictingBookings} conflicting CONFIRMED bookings for room {$roomName}");
            
            if ($conflictingBookings > 0) {
                // Getting details of conflicting bookings for debugging
                $detailsSql = "SELECT id, checkin_date, checkout_date, status FROM bookings 
                               WHERE room_name = ? 
                               AND status IN ('confirmed', 'paid')
                               AND (
                                   (checkin_date <= ? AND checkout_date > ?) OR
                                   (checkin_date < ? AND checkout_date >= ?) OR
                                   (checkin_date >= ? AND checkout_date <= ?)
                               )";
                $detailParams = [
                    $roomName,
                    $checkinDate, $checkinDate,
                    $checkoutDate, $checkoutDate,
                    $checkinDate, $checkoutDate
                ];
                if ($excludeId > 0) {
                    $detailsSql .= " AND id != ?";
                    $detailParams[] = $excludeId;
                }
                $details = fetchAll($conn, $detailsSql, $detailParams);
                error_log("Check availability: Conflicting bookings details: " . json_encode($details));
                return false; // There are conflicting confirmed bookings
            }
        }
        
        // Checking blocked dates (periods)
        // Checking whether the booking period overlaps with blocked periods
        // We take into account blocking for a specific room and blocking "__all__" (for all rooms and massage)
        // Safe SQL: first try with date_from/date_to, if it doesn’t work, use blocked_date
        $result = null;
        try {
            // Trying a request with date_from/date_to (new format)
            // Checking the locks for a specific room AND the "__all__" locks (for everyone)
            $sql = "SELECT COUNT(*) as count FROM blocked_dates 
                    WHERE (room_name = ? OR room_name = '__all__')
                    AND (
                        (COALESCE(date_from, blocked_date) <= ? AND COALESCE(date_to, blocked_date) >= ?) OR
                        (COALESCE(date_from, blocked_date) <= ? AND COALESCE(date_to, blocked_date) >= ?) OR
                        (COALESCE(date_from, blocked_date) >= ? AND COALESCE(date_to, blocked_date) < ?)
                    )";
            $result = fetchOne($conn, $sql, [$roomName, $checkinDate, $checkinDate, $checkoutDate, $checkoutDate, $checkinDate, $checkoutDate]);
        } catch (Exception $e) {
            // If the request fails (the fields do not exist), use the old format
            try {
                $sql = "SELECT COUNT(*) as count FROM blocked_dates 
                        WHERE (room_name = ? OR room_name = '__all__')
                        AND blocked_date >= ? 
                        AND blocked_date < ?";
                $result = fetchOne($conn, $sql, [$roomName, $checkinDate, $checkoutDate]);
            } catch (Exception $e2) {
                // If this does not work, we assume that the dates are not blocked
                error_log("Check availability: WARNING - Database error when checking blocked_dates, assuming available to avoid blocking");
                $result = ['count' => 0];
            }
        }
        
        if ($result === false) {
            error_log("Check availability: WARNING - Database error when checking blocked_dates, assuming available to avoid blocking");
        } else {
            $blockedDatesCount = isset($result['count']) ? intval($result['count']) : 0;
            error_log("Check availability: Found {$blockedDatesCount} manually blocked dates for room {$roomName}");
            
            if ($blockedDatesCount > 0) {
                return false; // There are blocked dates
            }
        }
        
        // Checking Airbnb synchronization (if any)
        $sql = "SELECT COUNT(*) as count FROM airbnb_calendar 
                WHERE room_name = ? 
                AND date >= ? 
                AND date < ? 
                AND is_available = 0";
        
        $result = fetchOne($conn, $sql, [$roomName, $checkinDate, $checkoutDate]);
        
        if ($result === false) {
            error_log("Check availability: WARNING - Database error when checking airbnb_calendar, assuming available to avoid blocking");
        } else {
            $airbnbBlockedCount = isset($result['count']) ? intval($result['count']) : 0;
            error_log("Check availability: Found {$airbnbBlockedCount} Airbnb blocked dates for room {$roomName}");
            
            if ($airbnbBlockedCount > 0) {
                return false; // Airbnb has blocked dates
            }
        }
        
        error_log("Check availability: Dates are available for room {$roomName} (check-in: {$checkinDate}, check-out: {$checkoutDate})");
        return true; // Dates available
        
    } catch (Exception $e) {
        // If there is an error, we consider the dates available so as not to block the creation of a reservation due to technical problems
        error_log("Check availability: EXCEPTION - " . $e->getMessage() . " - Assuming dates are available to avoid blocking");
        return true; // We return true on error so as not to block the booking
    }
}

/**
 * Nightly rate for booking totals: CMS merge for the four named rooms (same source as site price line).
 * For those rooms, returns null if no amount is configured — no substitution from rooms table or 150.
 *
 * @return float|null
 */
function getRoomPrice($conn, $roomName) {
    $cmsSlugByRoom = [
        'Loki Suite' => 'basement',
        'The Nouk' => 'ground_queen',
        'Vrienden' => 'ground_twin',
        'Kelder' => 'second',
    ];
    if (isset($cmsSlugByRoom[$roomName])) {
        $slug = $cmsSlugByRoom[$roomName];
        $row = fetchOne($conn, 'SELECT * FROM content_settings WHERE id = 1 LIMIT 1');
        if ($row && function_exists('btb_merge_phase1_canonical_into_content_row')) {
            btb_merge_phase1_canonical_into_content_row($conn, $row);
        }
        if ($row) {
            $n = btb_room_price_nightly_amount($row, $slug);
            if ($n !== null && $n > 0) {
                return $n;
            }
        }
        return null;
    }

    $sql = "SELECT price FROM rooms WHERE name = ? LIMIT 1";
    $result = fetchOne($conn, $sql, [$roomName]);
    if ($result && isset($result['price'])) {
        return floatval($result['price']);
    }

    return 150.00;
}

/**
 * One-line wellness price in CAD from CMS massage pricing JSON (same presets as massage.php / common.php).
 *
 * @return float
 */
function getMassageLinePrice($conn, $massageType, $durationMinutes) {
    $presetMap = [
        'Relaxing Massage' => 'relaxing',
        'Deep Tissue Massage' => 'deep_tissue',
        'Reiki Energy Healing' => 'reiki',
        'Sauna' => 'sauna',
    ];
    $t = trim((string) $massageType);
    if (!isset($presetMap[$t])) {
        return 0.0;
    }
    $preset = $presetMap[$t];
    $colByPreset = [
        'relaxing' => 'massage_pricing_relaxing',
        'deep_tissue' => 'massage_pricing_deep_tissue',
        'reiki' => 'massage_pricing_reiki',
        'sauna' => 'massage_pricing_sauna',
    ];
    $col = $colByPreset[$preset];
    $json = null;
    $row = fetchOne($conn, 'SELECT * FROM content_settings WHERE id = 1 LIMIT 1');
    if ($row && function_exists('btb_merge_phase1_canonical_into_content_row')) {
        btb_merge_phase1_canonical_into_content_row($conn, $row);
    }
    if ($row && array_key_exists($col, $row)) {
        $json = $row[$col];
    }
    if (!function_exists('btb_parse_massage_pricing')) {
        return 0.0;
    }
    $lines = btb_parse_massage_pricing(($json !== null && $json !== '') ? $json : null, $preset);
    $dur = (int) $durationMinutes;
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        if ((int) ($line['duration'] ?? 0) !== $dur) {
            continue;
        }
        $amt = trim((string) ($line['priceAmount'] ?? ''));
        if ($amt !== '' && is_numeric($amt)) {
            $n = (float) $amt;

            return $n >= 0 ? $n : 0.0;
        }
        $p = trim((string) ($line['price'] ?? ''));
        if (preg_match('/([\d]+(?:\.\d+)?)/', $p, $m)) {
            $n = (float) $m[1];

            return $n >= 0 ? $n : 0.0;
        }
    }

    return 0.0;
}

/**
 * @return array<string, bool>
 */
function btb_massage_bookings_column_set($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $chk = $conn->query('SHOW COLUMNS FROM massage_bookings');
        if ($chk) {
            while ($c = $chk->fetch_assoc()) {
                if (!empty($c['Field'])) {
                    $cache[$c['Field']] = true;
                }
            }
        }
    } catch (Exception $e) {
        $cache = [];
    }

    return $cache;
}

/**
 * Guest-facing confirmation reference: six digits as NNN-NNN (e.g. 482-903). Easy to read aloud.
 * Uniqueness is checked across room confirmations and massage bookings so the same string never appears twice.
 */
function btb_guest_confirmation_code_exists_anywhere($conn, string $code): bool
{
    $r = fetchOne($conn, 'SELECT 1 AS `x` FROM `booking_confirmations` WHERE `confirmation_code` = ? LIMIT 1', [$code]);
    if ($r) {
        return true;
    }
    $m = fetchOne($conn, 'SELECT 1 AS `x` FROM `massage_bookings` WHERE `confirmation_code` = ? LIMIT 1', [$code]);

    return (bool) $m;
}

function btb_allocate_unique_guest_confirmation_code($conn): string
{
    for ($attempt = 0; $attempt < 80; $attempt++) {
        $n = random_int(0, 999999);
        $d = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        $code = substr($d, 0, 3) . '-' . substr($d, 3, 3);
        if (!btb_guest_confirmation_code_exists_anywhere($conn, $code)) {
            return $code;
        }
    }
    // Extremely unlikely: fall back to time-based, still check collision
    $base = (int) ((microtime(true) * 1000) % 1000000);
    for ($j = 0; $j < 20; $j++) {
        $n = ($base + $j) % 1000000;
        $d = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        $code = substr($d, 0, 3) . '-' . substr($d, 3, 3);
        if (!btb_guest_confirmation_code_exists_anywhere($conn, $code)) {
            return $code;
        }
    }
    for ($k = 0; $k < 200; $k++) {
        $n = random_int(0, 999999);
        $d = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        $code = substr($d, 0, 3) . '-' . substr($d, 3, 3);
        if (!btb_guest_confirmation_code_exists_anywhere($conn, $code)) {
            return $code;
        }
    }
    error_log('btb_allocate_unique_guest_confirmation_code: extended retries, continuing with deep random search');
    for ($z = 0; $z < 5000; $z++) {
        $n = random_int(0, 999999);
        $d = str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        $code = substr($d, 0, 3) . '-' . substr($d, 3, 3);
        if (!btb_guest_confirmation_code_exists_anywhere($conn, $code)) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to allocate a unique guest confirmation code');
}

/** True if $posted matches $stored (allows typing with or without hyphen for numeric codes). */
function btb_confirmation_codes_match(string $posted, string $stored): bool
{
    $p = trim($posted);
    $s = trim($stored);
    if ($p === '' || $s === '') {
        return false;
    }
    if (preg_match('/^\d{3}-\d{3}$/', $s)) {
        $digits = preg_replace('/\D+/', '', $p);
        if (strlen($digits) === 6) {
            $canon = substr($digits, 0, 3) . '-' . substr($digits, 3, 3);

            return $canon === $s;
        }
    }

    return strcasecmp($p, $s) === 0;
}

/**
 * Generating a unique verification code for a room booking (stored in booking_confirmations).
 *
 * @param int $bookingId kept for callers; not part of the code anymore
 */
function generateConfirmationCode($bookingId) {
    global $conn;

    return btb_allocate_unique_guest_confirmation_code($conn);
}

/**
 * Receiving a reservation by ID
 */
function getBookingById($conn, $bookingId) {
    $sql = "SELECT b.*, bc.confirmation_code, bc.email_sent_at, bc.host_confirmed_at
            FROM bookings b
            LEFT JOIN booking_confirmations bc ON b.id = bc.booking_id
            WHERE b.id = ?";
    
    return fetchOne($conn, $sql, [$bookingId]);
}

/**
 * Receiving a reservation using a confirmation code
 */
function getBookingByConfirmationCode($conn, $confirmationCode) {
    $sql = "SELECT b.*, bc.confirmation_code, bc.email_sent_at, bc.host_confirmed_at
            FROM bookings b
            INNER JOIN booking_confirmations bc ON b.id = bc.booking_id
            WHERE bc.confirmation_code = ?";

    $code = trim((string) $confirmationCode);
    if ($code === '') {
        return null;
    }
    $row = fetchOne($conn, $sql, [$code]);
    if ($row) {
        return $row;
    }
    $digits = preg_replace('/\D+/', '', $code);
    if (strlen($digits) === 6) {
        $canon = substr($digits, 0, 3) . '-' . substr($digits, 3, 3);
        if ($canon !== $code) {
            return fetchOne($conn, $sql, [$canon]);
        }
    }

    return null;
}

/**
 * Checking administrator authorization (temporary stub)
 */
function isAdminAuthenticated() {
    // TODO: Implement real authorization check
    // For now we always return true for development
    return true;
}

?>

