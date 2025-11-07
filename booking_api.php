<?php
/**
 * Booking API Handler
 * Обработка всех API запросов, связанных с бронированиями
 */

require_once 'common.php';
require_once 'payment_service.php';
require_once 'email_service.php';
require_once 'airbnb_sync.php';

// Получаем действие из запроса
$action = getApiAction();

// Маршрутизация действий
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
    
    default:
        // Если действие не связано с бронированиями, не обрабатываем
        break;
}

/**
 * Проверка доступности дат для комнаты
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
        
        // Валидация дат
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
        
        // Проверяем доступность
        // ИСПРАВЛЕНО: Учитываем только подтвержденные (confirmed) бронирования
        // pending бронирования НЕ блокируют даты, чтобы не мешать другим клиентам
        error_log("Check availability: Room={$roomName}, Check-in={$checkinDate}, Check-out={$checkoutDate}");
        $isAvailable = checkDateAvailability($conn, $roomName, $checkinDate, $checkoutDate);
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
 * Создание нового бронирования
 */
function handleCreateBooking() {
    global $conn;
    
    try {
        // Получаем данные из запроса
        $data = [
            'room_name' => sanitizeInput($_POST['room_name'] ?? ''),
            'checkin_date' => sanitizeInput($_POST['checkin_date'] ?? ''),
            'checkout_date' => sanitizeInput($_POST['checkout_date'] ?? ''),
            'guest_name' => sanitizeInput($_POST['guest_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'guests_count' => intval($_POST['guests_count'] ?? 1),
            'pets' => isset($_POST['pets']) && $_POST['pets'] ? 1 : 0,
            'special_requests' => sanitizeInput($_POST['special_requests'] ?? '')
        ];
        
        // Валидация обязательных полей
        $required = ['room_name', 'checkin_date', 'checkout_date', 'guest_name', 'email', 'phone'];
        $errors = validateRequired($data, $required);
        
        if (!empty($errors)) {
            sendError('Validation failed: ' . implode(', ', $errors));
        }
        
        // Валидация email
        if (!validateEmail($data['email'])) {
            sendError('Invalid email address');
        }
        
        // Валидация телефона
        if (!validatePhone($data['phone'])) {
            sendError('Invalid phone number');
        }
        
        // Валидация дат
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
        
        // Проверяем доступность дат
        error_log("Create booking: Checking availability for room {$data['room_name']}, check-in: {$data['checkin_date']}, check-out: {$data['checkout_date']}");
        $isAvailable = checkDateAvailability($conn, $data['room_name'], $data['checkin_date'], $data['checkout_date']);
        error_log("Create booking: Availability check result: " . ($isAvailable ? 'available' : 'not available'));
        
        if (!$isAvailable) {
            error_log("Create booking: Dates are not available, aborting booking creation");
            sendError('Selected dates are not available');
        }
        
        error_log("Create booking: Dates are available, proceeding with booking creation");
        
        // Рассчитываем стоимость (временно используем базовую цену из таблицы rooms)
        $roomPrice = getRoomPrice($conn, $data['room_name']);
        $nights = $checkin->diff($checkout)->days;
        $totalAmount = $roomPrice * $nights;
        error_log("Create booking: Calculated price: {$roomPrice} CAD/night, {$nights} nights, total: {$totalAmount} CAD");
        
        // Подготавливаем данные для вставки
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
        
        // Создаем бронирование
        error_log("Create booking: Attempting to insert booking record into database");
        $bookingId = insertRecord($conn, 'bookings', $bookingData);
        
        if (!$bookingId) {
            error_log("Create booking: ERROR - Failed to insert booking record into database");
            sendError('Failed to create booking');
        }
        
        error_log("Create booking: Booking record created successfully with ID: {$bookingId}");
        
        // Генерируем код подтверждения
        $confirmationCode = generateConfirmationCode($bookingId);
        error_log("Create booking: Generated confirmation code: {$confirmationCode}");
        
        // Создаем запись подтверждения
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
        
        // Получаем полную информацию о бронировании
        error_log("Create booking: Fetching full booking details for ID: {$bookingId}");
        $booking = getBookingById($conn, $bookingId);
        
        if (!$booking) {
            error_log("Create booking: ERROR - Failed to fetch booking details after creation");
            sendError('Failed to retrieve booking details');
        }
        
        error_log("Create booking: Booking details retrieved successfully. Email: " . ($booking['email'] ?? 'N/A'));
        
        // Создаем Payment Intent для оплаты (если Stripe настроен)
        $paymentIntent = null;
        $clientSecret = null;
        
        if (!empty(STRIPE_SECRET_KEY) && $totalAmount > 0) {
            try {
                $paymentResult = createPaymentIntent($booking);
                if ($paymentResult['success']) {
                    $paymentIntent = $paymentResult['payment_intent_id'];
                    $clientSecret = $paymentResult['client_secret'];
                } else {
                    // Логируем ошибку создания Payment Intent, но не прерываем процесс
                    logActivity("Failed to create Payment Intent: " . ($paymentResult['error'] ?? 'Unknown error'), 'WARNING');
                }
            } catch (Exception $e) {
                // Логируем ошибку, но не прерываем процесс создания бронирования
                logActivity("Payment Intent creation error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // Отправляем email подтверждения гостю (если email сервис настроен)
        if (!empty(MAILGUN_API_KEY)) {
            error_log("Booking API: Attempting to send confirmation email to guest: " . ($booking['email'] ?? 'N/A'));
            try {
                $emailResult = sendBookingConfirmation($booking);
                error_log("Booking API: Guest confirmation email result: " . json_encode($emailResult));
                if (!$emailResult || !$emailResult['success']) {
                    error_log("Booking API: Guest confirmation email failed: " . ($emailResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // Логируем ошибку email, но не прерываем процесс создания бронирования
                error_log("Booking API: Exception sending guest confirmation email: " . $e->getMessage());
                logActivity("Failed to send booking confirmation email: " . $e->getMessage(), 'WARNING');
            }
        } else {
            error_log("Booking API: MAILGUN_API_KEY is empty, skipping email sending");
        }
        
        // Отправляем уведомление хозяину о новом бронировании (если email сервис настроен)
        if (!empty(MAILGUN_API_KEY)) {
            error_log("Booking API: Attempting to send notification email to host: " . (defined('MAILGUN_HOST_EMAIL') ? MAILGUN_HOST_EMAIL : 'N/A'));
            try {
                $hostEmailResult = sendBookingRequestToHost($booking);
                error_log("Booking API: Host notification email result: " . json_encode($hostEmailResult));
                if (!$hostEmailResult || !$hostEmailResult['success']) {
                    error_log("Booking API: Host notification email failed: " . ($hostEmailResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // Логируем ошибку email, но не прерываем процесс
                error_log("Booking API: Exception sending host notification email: " . $e->getMessage());
                logActivity("Failed to send booking request to host: " . $e->getMessage(), 'WARNING');
            }
        } else {
            error_log("Booking API: MAILGUN_API_KEY is empty, skipping host email");
        }
        
        // Логируем создание бронирования
        logActivity("Booking created: ID {$bookingId}, Room: {$data['room_name']}, Guest: {$data['guest_name']}");
        
        $response = [
            'booking_id' => $bookingId,
            'confirmation_code' => $confirmationCode,
            'booking' => $booking,
            'message' => 'Booking created successfully'
        ];
        
        // Добавляем данные платежа в ответ
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
 * Получение бронирования по ID
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
 * Подтверждение бронирования администратором
 */
function handleConfirmBooking() {
    global $conn;
    
    try {
        // Проверка авторизации администратора
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // Получаем бронирование
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        if ($booking['status'] !== 'pending') {
            sendError('Booking cannot be confirmed. Current status: ' . $booking['status']);
        }
        
        // Обновляем статус
        $updateData = [
            'status' => 'confirmed',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to confirm booking');
        }
        
        // Обновляем время подтверждения хозяином
        updateRecord($conn, 'booking_confirmations', 
            ['host_confirmed_at' => date('Y-m-d H:i:s')], 
            'booking_id = ?', 
            [$bookingId]
        );
        
        // Логируем
        logActivity("Booking confirmed: ID {$bookingId}");
        
        // Получаем обновленное бронирование
        $updatedBooking = getBookingById($conn, $bookingId);
        
        // Отправляем email подтверждения гостю после одобрения хозяином
        if (!empty(MAILGUN_API_KEY)) {
            try {
                sendBookingConfirmedToGuest($updatedBooking);
            } catch (Exception $e) {
                // Логируем ошибку email, но не прерываем процесс
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
 * Отмена бронирования
 */
function handleCancelBooking() {
    global $conn;
    
    try {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // Получаем бронирование
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        if ($booking['status'] === 'cancelled') {
            sendError('Booking is already cancelled');
        }
        
        // Обновляем статус
        $updateData = [
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to cancel booking');
        }
        
        // Если была оплата, обрабатываем возврат средств через Stripe
        if ($booking['payment_status'] === 'paid' && !empty(STRIPE_SECRET_KEY)) {
            try {
                $refundResult = refundPayment($bookingId);
                if (!$refundResult['success']) {
                    // Логируем ошибку возврата, но продолжаем отмену бронирования
                    logActivity("Failed to refund payment: " . ($refundResult['error'] ?? 'Unknown error'), 'WARNING');
                } else {
                    logActivity("Payment refunded successfully: Booking {$bookingId}, Amount: {$refundResult['amount']}");
                }
            } catch (Exception $e) {
                // Логируем ошибку, но не прерываем процесс отмены
                logActivity("Refund error: " . $e->getMessage(), 'WARNING');
            }
        }
        
        logActivity("Booking cancelled: ID {$bookingId}, Reason: {$reason}");
        
        $updatedBooking = getBookingById($conn, $bookingId);
        
        // Отправляем email об отмене бронирования гостю
        if (!empty(MAILGUN_API_KEY)) {
            try {
                sendBookingCancelled($updatedBooking, $reason);
            } catch (Exception $e) {
                // Логируем ошибку email, но не прерываем процесс
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
 * Удаление бронирования (полное удаление из базы данных)
 */
function handleDeleteBooking() {
    global $conn;
    
    try {
        // Проверка авторизации администратора
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if ($bookingId <= 0) {
            sendError('Invalid booking ID');
        }
        
        // Получаем бронирование перед удалением для логирования
        $booking = getBookingById($conn, $bookingId);
        if (!$booking) {
            sendError('Booking not found');
        }
        
        // Удаляем связанные записи (booking_confirmations удалится автоматически из-за CASCADE)
        // Но лучше удалить явно для безопасности
        deleteRecord($conn, 'booking_confirmations', 'booking_id = ?', [$bookingId]);
        
        // Удаляем само бронирование
        $result = deleteRecord($conn, 'bookings', 'id = ?', [$bookingId]);
        
        if (!$result) {
            sendError('Failed to delete booking');
        }
        
        logActivity("Booking deleted permanently: ID {$bookingId}, Room: {$booking['room_name']}, Guest: {$booking['guest_name']}");
        
        sendSuccess([
            'message' => 'Booking deleted successfully'
        ]);
        
    } catch (Exception $e) {
        logActivity("Delete booking error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to delete booking: ' . $e->getMessage());
    }
}

/**
 * Получение списка всех бронирований (для админ-панели)
 */
function handleGetBookings() {
    global $conn;
    
    try {
        // Проверка авторизации администратора (только для полного списка)
        // Для публичного календаря доступности авторизация не требуется, только фильтр по комнате
        $roomName = sanitizeInput($_GET['room_name'] ?? $_POST['room_name'] ?? '');
        $isPublicRequest = !empty($roomName); // Публичный запрос - только для конкретной комнаты
        
        if (!$isPublicRequest && !isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        // Фильтры
        $status = sanitizeInput($_GET['status'] ?? '');
        // $roomName уже определена выше для проверки авторизации
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
 * Блокировка даты вручную
 */
function handleBlockDate() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $roomName = sanitizeInput($_POST['room_name'] ?? '');
        $blockedDate = sanitizeInput($_POST['blocked_date'] ?? '');
        $reason = sanitizeInput($_POST['reason'] ?? 'Manually blocked by admin');
        
        if (empty($roomName) || empty($blockedDate)) {
            sendError('Room name and blocked date are required');
        }
        
        // Проверяем валидность даты
        $date = DateTime::createFromFormat('Y-m-d', $blockedDate);
        if (!$date) {
            sendError('Invalid date format');
        }
        
        // Проверяем, не заблокирована ли уже эта дата
        $existing = fetchOne($conn, 
            "SELECT * FROM blocked_dates WHERE room_name = ? AND blocked_date = ?",
            [$roomName, $blockedDate]
        );
        
        if ($existing) {
            sendError('Date is already blocked');
        }
        
        // Создаем блокировку
        $blockData = [
            'room_name' => $roomName,
            'blocked_date' => $blockedDate,
            'reason' => $reason
        ];
        
        $blockId = insertRecord($conn, 'blocked_dates', $blockData);
        
        if (!$blockId) {
            sendError('Failed to block date');
        }
        
        logActivity("Date blocked: Room {$roomName}, Date {$blockedDate}");
        
        sendSuccess(['block_id' => $blockId, 'message' => 'Date blocked successfully']);
        
    } catch (Exception $e) {
        logActivity("Block date error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to block date: ' . $e->getMessage());
    }
}

/**
 * Разблокировка даты
 */
function handleUnblockDate() {
    global $conn;
    
    try {
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $blockId = intval($_POST['block_id'] ?? 0);
        
        if ($blockId <= 0) {
            sendError('Invalid block ID');
        }
        
        $result = deleteRecord($conn, 'blocked_dates', 'id = ?', [$blockId]);
        
        if (!$result) {
            sendError('Failed to unblock date');
        }
        
        logActivity("Date unblocked: Block ID {$blockId}");
        
        sendSuccess(['message' => 'Date unblocked successfully']);
        
    } catch (Exception $e) {
        logActivity("Unblock date error: " . $e->getMessage(), 'ERROR');
        sendError('Failed to unblock date: ' . $e->getMessage());
    }
}

/**
 * Получение заблокированных дат
 */
function handleGetBlockedDates() {
    global $conn;
    
    try {
        $roomName = sanitizeInput($_GET['room_name'] ?? $_POST['room_name'] ?? '');
        
        // Получаем ручные блокировки
        $where = [];
        $params = [];
        
        if (!empty($roomName)) {
            $where[] = 'room_name = ?';
            $params[] = $roomName;
        }
        
        $whereClause = !empty($where) ? implode(' AND ', $where) : '1=1';
        $sql = "SELECT * FROM blocked_dates WHERE {$whereClause} ORDER BY blocked_date ASC";
        
        $blockedDates = fetchAll($conn, $sql, $params);
        
        // Также получаем Airbnb заблокированные даты
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
 * Синхронизация календаря Airbnb
 */
function handleSyncAirbnb() {
    global $conn;
    
    try {
        // Проверка авторизации администратора
        if (!isAdminAuthenticated()) {
            sendError('Admin authentication required');
        }
        
        $roomName = sanitizeInput($_POST['room_name'] ?? $_GET['room_name'] ?? '');
        $roomName = !empty($roomName) ? $roomName : null;
        
        // Выполняем синхронизацию
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
 * Получение статуса синхронизации Airbnb
 */
function handleGetAirbnbSyncStatus() {
    global $conn;
    
    try {
        // Проверка авторизации администратора (для полного статуса)
        $roomName = sanitizeInput($_GET['room_name'] ?? '');
        $roomName = !empty($roomName) ? $roomName : null;
        
        // Для публичного доступа (конкретная комната) авторизация не требуется
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

// ==========================================
// Вспомогательные функции
// ==========================================

/**
 * Проверка доступности дат для комнаты
 */
function checkDateAvailability($conn, $roomName, $checkinDate, $checkoutDate) {
    try {
        // Проверяем существующие бронирования
        // ИСПРАВЛЕНО: Учитываем только подтвержденные (confirmed) бронирования
        // pending бронирования НЕ блокируют даты, чтобы не мешать другим клиентам
        $sql = "SELECT COUNT(*) as count FROM bookings 
                WHERE room_name = ? 
                AND status IN ('confirmed', 'paid')
                AND (
                    (checkin_date <= ? AND checkout_date > ?) OR
                    (checkin_date < ? AND checkout_date >= ?) OR
                    (checkin_date >= ? AND checkout_date <= ?)
                )";
        
        $result = fetchOne($conn, $sql, [
            $roomName, 
            $checkinDate, $checkinDate,
            $checkoutDate, $checkoutDate,
            $checkinDate, $checkoutDate
        ]);
        
        // Если fetchOne вернул false из-за ошибки, считаем что даты доступны (чтобы не блокировать из-за ошибки БД)
        if ($result === false) {
            error_log("Check availability: WARNING - Database error when checking bookings, assuming available to avoid blocking");
            // НЕ возвращаем false при ошибке БД, чтобы не блокировать создание бронирования
        } else {
            $conflictingBookings = isset($result['count']) ? intval($result['count']) : 0;
            error_log("Check availability: Found {$conflictingBookings} conflicting CONFIRMED bookings for room {$roomName}");
            
            if ($conflictingBookings > 0) {
                // Получаем детали конфликтующих бронирований для отладки
                $detailsSql = "SELECT id, checkin_date, checkout_date, status FROM bookings 
                               WHERE room_name = ? 
                               AND status IN ('confirmed', 'paid')
                               AND (
                                   (checkin_date <= ? AND checkout_date > ?) OR
                                   (checkin_date < ? AND checkout_date >= ?) OR
                                   (checkin_date >= ? AND checkout_date <= ?)
                               )";
                $details = fetchAll($conn, $detailsSql, [
                    $roomName, 
                    $checkinDate, $checkinDate,
                    $checkoutDate, $checkoutDate,
                    $checkinDate, $checkoutDate
                ]);
                error_log("Check availability: Conflicting bookings details: " . json_encode($details));
                return false; // Есть конфликтующие подтвержденные бронирования
            }
        }
        
        // Проверяем заблокированные даты
        $sql = "SELECT COUNT(*) as count FROM blocked_dates 
                WHERE room_name = ? 
                AND blocked_date >= ? 
                AND blocked_date < ?";
        
        $result = fetchOne($conn, $sql, [$roomName, $checkinDate, $checkoutDate]);
        
        if ($result === false) {
            error_log("Check availability: WARNING - Database error when checking blocked_dates, assuming available to avoid blocking");
        } else {
            $blockedDatesCount = isset($result['count']) ? intval($result['count']) : 0;
            error_log("Check availability: Found {$blockedDatesCount} manually blocked dates for room {$roomName}");
            
            if ($blockedDatesCount > 0) {
                return false; // Есть заблокированные даты
            }
        }
        
        // Проверяем синхронизацию Airbnb (если есть)
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
                return false; // Есть заблокированные даты в Airbnb
            }
        }
        
        error_log("Check availability: Dates are available for room {$roomName} (check-in: {$checkinDate}, check-out: {$checkoutDate})");
        return true; // Даты доступны
        
    } catch (Exception $e) {
        // При ошибке считаем даты доступными, чтобы не блокировать создание бронирования из-за технических проблем
        error_log("Check availability: EXCEPTION - " . $e->getMessage() . " - Assuming dates are available to avoid blocking");
        return true; // Возвращаем true при ошибке, чтобы не блокировать бронирование
    }
}

/**
 * Получение цены комнаты
 */
function getRoomPrice($conn, $roomName) {
    // Пытаемся получить цену из таблицы rooms
    $sql = "SELECT price FROM rooms WHERE name = ? LIMIT 1";
    $result = fetchOne($conn, $sql, [$roomName]);
    
    if ($result && isset($result['price'])) {
        return floatval($result['price']);
    }
    
    // Если комната не найдена, используем стандартную цену
    // TODO: Можно добавить конфигурацию стандартных цен
    return 150.00; // Стандартная цена за ночь в CAD
}

/**
 * Генерация уникального кода подтверждения
 */
function generateConfirmationCode($bookingId) {
    // Формат: BTB-{timestamp}-{bookingId}
    $timestamp = time();
    $code = 'BTB-' . $timestamp . '-' . str_pad($bookingId, 4, '0', STR_PAD_LEFT);
    return $code;
}

/**
 * Получение бронирования по ID
 */
function getBookingById($conn, $bookingId) {
    $sql = "SELECT b.*, bc.confirmation_code, bc.email_sent_at, bc.host_confirmed_at
            FROM bookings b
            LEFT JOIN booking_confirmations bc ON b.id = bc.booking_id
            WHERE b.id = ?";
    
    return fetchOne($conn, $sql, [$bookingId]);
}

/**
 * Получение бронирования по коду подтверждения
 */
function getBookingByConfirmationCode($conn, $confirmationCode) {
    $sql = "SELECT b.*, bc.confirmation_code, bc.email_sent_at, bc.host_confirmed_at
            FROM bookings b
            INNER JOIN booking_confirmations bc ON b.id = bc.booking_id
            WHERE bc.confirmation_code = ?";
    
    return fetchOne($conn, $sql, [$confirmationCode]);
}

/**
 * Проверка авторизации администратора (временная заглушка)
 */
function isAdminAuthenticated() {
    // TODO: Реализовать реальную проверку авторизации
    // Пока всегда возвращаем true для разработки
    return true;
}

?>

