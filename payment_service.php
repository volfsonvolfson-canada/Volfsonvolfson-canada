<?php
/**
 * Payment Service
 * Сервис для обработки платежей через Stripe
 */

require_once 'config.php';
require_once 'common.php';

// Проверяем, что Stripe ключи настроены
if (empty(STRIPE_SECRET_KEY)) {
    error_log("Stripe Secret Key is not configured");
    throw new Exception("Stripe Secret Key is not configured");
}

/**
 * Инициализация Stripe клиента
 * 
 * Для работы этого сервиса нужно установить Stripe PHP SDK через Composer:
 * composer require stripe/stripe-php
 * 
 * Или загрузить вручную и подключить:
 * require_once 'path/to/stripe-php/init.php';
 */
function getStripeClient() {
    // Проверяем, установлен ли Stripe SDK
    if (class_exists('\Stripe\Stripe')) {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        return new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }
    
    // Если Stripe SDK не установлен, используем прямые вызовы API
    // Это менее удобно, но работает без Composer
    return null;
}

/**
 * Создание Payment Intent для бронирования
 * 
 * @param array $booking Данные бронирования
 * @return array Результат создания Payment Intent
 */
function createPaymentIntent($booking) {
    global $conn;
    
    try {
        $bookingId = $booking['id'] ?? $booking['booking_id'] ?? null;
        $amount = floatval($booking['total_amount'] ?? 0);
        $currency = strtolower($booking['currency'] ?? STRIPE_CURRENCY);
        $email = $booking['email'] ?? '';
        
        if ($bookingId <= 0) {
            throw new Exception('Invalid booking ID');
        }
        
        if ($amount <= 0) {
            throw new Exception('Invalid payment amount');
        }
        
        // Конвертируем сумму в центы (Stripe использует центы)
        $amountInCents = intval(round($amount * 100));
        
        // Создаем Payment Intent через Stripe API
        $stripeClient = getStripeClient();
        
        if ($stripeClient) {
            // Используем Stripe SDK (если установлен)
            $paymentIntent = $stripeClient->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'booking_id' => $bookingId,
                    'room_name' => $booking['room_name'] ?? '',
                    'guest_name' => $booking['guest_name'] ?? '',
                    'checkin_date' => $booking['checkin_date'] ?? '',
                    'checkout_date' => $booking['checkout_date'] ?? ''
                ],
                'receipt_email' => $email,
                'description' => "Booking #{$bookingId} - " . ($booking['room_name'] ?? 'Room'),
                'automatic_payment_methods' => [
                    'enabled' => true
                ]
            ]);
            
            $paymentIntentId = $paymentIntent->id;
            $clientSecret = $paymentIntent->client_secret;
        } else {
            // Используем прямые вызовы API (без SDK)
            $paymentIntentId = createPaymentIntentViaAPI($amountInCents, $currency, $bookingId, $booking, $email);
            $clientSecret = getPaymentIntentClientSecret($paymentIntentId);
        }
        
        // Обновляем бронирование с Payment Intent ID
        if ($bookingId) {
            updateRecord($conn, 'bookings', [
                'payment_intent_id' => $paymentIntentId,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$bookingId]);
        }
        
        // Логируем создание Payment Intent
        logActivity("Payment Intent created: {$paymentIntentId}, Booking: {$bookingId}, Amount: {$amount} {$currency}");
        
        return [
            'success' => true,
            'payment_intent_id' => $paymentIntentId,
            'client_secret' => $clientSecret,
            'amount' => $amount,
            'currency' => strtoupper($currency)
        ];
        
    } catch (Exception $e) {
        logActivity("Create Payment Intent error: " . $e->getMessage(), 'ERROR');
        error_log("Payment Intent creation failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Создание Payment Intent через прямой вызов API (без SDK)
 */
function createPaymentIntentViaAPI($amountInCents, $currency, $bookingId, $booking, $email) {
    $url = 'https://api.stripe.com/v1/payment_intents';
    
    $data = [
        'amount' => $amountInCents,
        'currency' => $currency,
        'metadata[booking_id]' => $bookingId,
        'metadata[room_name]' => $booking['room_name'] ?? '',
        'metadata[guest_name]' => $booking['guest_name'] ?? '',
        'metadata[checkin_date]' => $booking['checkin_date'] ?? '',
        'metadata[checkout_date]' => $booking['checkout_date'] ?? '',
        'receipt_email' => $email,
        'description' => "Booking #{$bookingId} - " . ($booking['room_name'] ?? 'Room'),
        'automatic_payment_methods[enabled]' => 'true'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        throw new Exception($error['error']['message'] ?? 'Failed to create payment intent');
    }
    
    $result = json_decode($response, true);
    return $result['id'];
}

/**
 * Получение Client Secret для Payment Intent
 */
function getPaymentIntentClientSecret($paymentIntentId) {
    $url = "https://api.stripe.com/v1/payment_intents/{$paymentIntentId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to retrieve payment intent');
    }
    
    $result = json_decode($response, true);
    return $result['client_secret'] ?? '';
}

/**
 * Обработка успешного платежа
 * 
 * @param string $paymentIntentId ID Payment Intent
 * @return array Результат обработки
 */
function processSuccessfulPayment($paymentIntentId) {
    global $conn;
    
    try {
        // Получаем информацию о Payment Intent
        $paymentIntent = retrievePaymentIntent($paymentIntentId);
        
        if (!$paymentIntent) {
            throw new Exception('Payment Intent not found');
        }
        
        $bookingId = $paymentIntent['metadata']['booking_id'] ?? null;
        
        if (!$bookingId) {
            throw new Exception('Booking ID not found in payment metadata');
        }
        
        // Обновляем статус оплаты бронирования
        $updateData = [
            'payment_status' => 'paid',
            'stripe_payment_id' => $paymentIntentId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            throw new Exception('Failed to update booking payment status');
        }
        
        // Логируем успешную оплату
        logActivity("Payment successful: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
        
        // Получаем обновленное бронирование
        $booking = fetchOne($conn, "SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        
        return [
            'success' => true,
            'booking_id' => $bookingId,
            'payment_intent_id' => $paymentIntentId,
            'booking' => $booking
        ];
        
    } catch (Exception $e) {
        logActivity("Process payment error: " . $e->getMessage(), 'ERROR');
        error_log("Payment processing failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получение информации о Payment Intent
 */
function retrievePaymentIntent($paymentIntentId) {
    $url = "https://api.stripe.com/v1/payment_intents/{$paymentIntentId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null;
    }
    
    return json_decode($response, true);
}

/**
 * Обработка возврата средств при отмене бронирования
 * 
 * @param int $bookingId ID бронирования
 * @param float $amount Сумма возврата (опционально, по умолчанию полный возврат)
 * @return array Результат возврата
 */
function refundPayment($bookingId, $amount = null) {
    global $conn;
    
    try {
        // Получаем бронирование
        $booking = fetchOne($conn, "SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        
        if (!$booking) {
            throw new Exception('Booking not found');
        }
        
        if ($booking['payment_status'] !== 'paid') {
            throw new Exception('Booking is not paid, cannot refund');
        }
        
        $paymentIntentId = $booking['payment_intent_id'] ?? null;
        
        if (!$paymentIntentId) {
            throw new Exception('Payment Intent ID not found');
        }
        
        // Определяем сумму возврата
        $refundAmount = $amount !== null ? floatval($amount) : floatval($booking['total_amount']);
        $refundAmountInCents = intval(round($refundAmount * 100));
        
        // Создаем возврат через Stripe API
        $url = "https://api.stripe.com/v1/refunds";
        
        $data = [
            'payment_intent' => $paymentIntentId,
            'amount' => $refundAmountInCents,
            'reason' => 'requested_by_customer',
            'metadata[booking_id]' => $bookingId,
            'metadata[reason]' => 'Booking cancellation'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new Exception($error['error']['message'] ?? 'Failed to process refund');
        }
        
        $refund = json_decode($response, true);
        
        // Обновляем статус оплаты бронирования
        $updateData = [
            'payment_status' => 'refunded',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        // Логируем возврат
        logActivity("Payment refunded: Booking {$bookingId}, Amount: {$refundAmount}, Refund ID: {$refund['id']}");
        
        return [
            'success' => true,
            'refund_id' => $refund['id'],
            'amount' => $refundAmount,
            'booking_id' => $bookingId
        ];
        
    } catch (Exception $e) {
        logActivity("Refund payment error: " . $e->getMessage(), 'ERROR');
        error_log("Refund processing failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Проверка статуса платежа
 * 
 * @param string $paymentIntentId ID Payment Intent
 * @return array Статус платежа
 */
function checkPaymentStatus($paymentIntentId) {
    try {
        $paymentIntent = retrievePaymentIntent($paymentIntentId);
        
        if (!$paymentIntent) {
            return [
                'success' => false,
                'error' => 'Payment Intent not found'
            ];
        }
        
        return [
            'success' => true,
            'status' => $paymentIntent['status'],
            'amount' => $paymentIntent['amount'] / 100, // Конвертируем из центов
            'currency' => strtoupper($paymentIntent['currency']),
            'metadata' => $paymentIntent['metadata'] ?? []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

?>



