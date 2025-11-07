<?php
/**
 * Stripe Webhook Handler
 * Обработчик webhook событий от Stripe
 * 
 * Настройка webhook в Stripe Dashboard:
 * 1. Developers → Webhooks → Add endpoint
 * 2. Endpoint URL: https://new.backtobase.ca/stripe_webhook.php
 * 3. Events to send: payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled
 * 4. Скопируйте Signing secret и добавьте в config.php как STRIPE_WEBHOOK_SECRET
 */

require_once 'config.php';
require_once 'common.php';
require_once 'payment_service.php';

// Подключаем email сервис если настроен Mailgun
if (!empty(MAILGUN_API_KEY)) {
    require_once 'email_service.php';
}

// Получаем raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sig_header)) {
    http_response_code(400);
    error_log("Stripe webhook: Missing payload or signature");
    exit;
}

// Проверяем подпись webhook
if (!empty(STRIPE_WEBHOOK_SECRET)) {
    try {
        verifyWebhookSignature($payload, $sig_header, STRIPE_WEBHOOK_SECRET);
    } catch (Exception $e) {
        http_response_code(400);
        error_log("Stripe webhook signature verification failed: " . $e->getMessage());
        exit;
    }
} else {
    // Если webhook secret не настроен, логируем предупреждение но продолжаем
    error_log("Warning: STRIPE_WEBHOOK_SECRET is not configured. Webhook signature verification skipped.");
}

// Декодируем JSON payload
$event = json_decode($payload, true);

if (!$event) {
    http_response_code(400);
    error_log("Stripe webhook: Invalid JSON payload");
    exit;
}

// Логируем событие
logActivity("Stripe webhook received: {$event['type']}, ID: {$event['id']}");

// Обрабатываем событие
try {
    $eventType = $event['type'] ?? '';
    $eventData = $event['data']['object'] ?? [];
    
    switch ($eventType) {
        case 'payment_intent.succeeded':
            handlePaymentIntentSucceeded($eventData);
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentIntentFailed($eventData);
            break;
            
        case 'payment_intent.canceled':
            handlePaymentIntentCanceled($eventData);
            break;
            
        default:
            // Логируем неизвестное событие, но не возвращаем ошибку
            logActivity("Stripe webhook: Unknown event type: {$eventType}", 'INFO');
            break;
    }
    
    // Возвращаем успешный ответ Stripe
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Exception $e) {
    // Логируем ошибку, но возвращаем успешный ответ Stripe
    // (чтобы Stripe не повторял событие бесконечно)
    error_log("Stripe webhook processing error: " . $e->getMessage());
    logActivity("Stripe webhook processing error: " . $e->getMessage(), 'ERROR');
    
    http_response_code(200);
    echo json_encode(['received' => true, 'error' => $e->getMessage()]);
}

/**
 * Проверка подписи webhook
 */
function verifyWebhookSignature($payload, $sig_header, $secret) {
    // Если установлен Stripe SDK, используем его для проверки
    if (class_exists('\Stripe\Webhook')) {
        try {
            \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
            return true;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    // Иначе проверяем вручную (упрощенная версия)
    // Для продакшена рекомендуется использовать Stripe SDK
    $timestamp = '';
    $signatures = explode(',', $sig_header);
    
    foreach ($signatures as $signature) {
        $parts = explode('=', trim($signature));
        if (count($parts) === 2) {
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $expected_signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
                if (hash_equals($expected_signature, $parts[1])) {
                    return true;
                }
            }
        }
    }
    
    throw new Exception('Invalid webhook signature');
}

/**
 * Обработка успешного платежа
 */
function handlePaymentIntentSucceeded($paymentIntent) {
    global $conn;
    
    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';
        $bookingId = $paymentIntent['metadata']['booking_id'] ?? null;
        
        if (!$bookingId) {
            throw new Exception("Booking ID not found in payment intent metadata");
        }
        
        // Обрабатываем платеж
        $result = processSuccessfulPayment($paymentIntentId);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process payment');
        }
        
        // Получаем бронирование
        $booking = fetchOne($conn, "SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        
        if (!$booking) {
            throw new Exception("Booking not found: {$bookingId}");
        }
        
        // Отправляем email подтверждения (если email сервис настроен)
        if (!empty(MAILGUN_API_KEY) && function_exists('sendBookingConfirmation')) {
            try {
                sendBookingConfirmation($booking);
            } catch (Exception $e) {
                // Логируем ошибку email, но не прерываем обработку
                error_log("Failed to send booking confirmation email: " . $e->getMessage());
            }
        }
        
        logActivity("Payment intent succeeded processed: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
        
    } catch (Exception $e) {
        error_log("Error handling payment_intent.succeeded: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Обработка неудачного платежа
 */
function handlePaymentIntentFailed($paymentIntent) {
    global $conn;
    
    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';
        $bookingId = $paymentIntent['metadata']['booking_id'] ?? null;
        
        if (!$bookingId) {
            return; // Не критично, просто логируем
        }
        
        // Обновляем статус оплаты на failed
        updateRecord($conn, 'bookings', [
            'payment_status' => 'failed',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$bookingId]);
        
        logActivity("Payment intent failed: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
        
    } catch (Exception $e) {
        error_log("Error handling payment_intent.payment_failed: " . $e->getMessage());
    }
}

/**
 * Обработка отмененного платежа
 */
function handlePaymentIntentCanceled($paymentIntent) {
    global $conn;
    
    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';
        $bookingId = $paymentIntent['metadata']['booking_id'] ?? null;
        
        if (!$bookingId) {
            return; // Не критично, просто логируем
        }
        
        // Обновляем статус оплаты
        updateRecord($conn, 'bookings', [
            'payment_status' => 'pending',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$bookingId]);
        
        logActivity("Payment intent canceled: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
        
    } catch (Exception $e) {
        error_log("Error handling payment_intent.canceled: " . $e->getMessage());
    }
}

?>

