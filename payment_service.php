<?php
/**
 * Payment Service
 * Service for processing payments via Stripe
 */

require_once 'config.php';
require_once 'common.php';

// Checking that Stripe keys are configured
if (empty(STRIPE_SECRET_KEY)) {
    error_log("Stripe Secret Key is not configured");
    throw new Exception("Stripe Secret Key is not configured");
}

/**
 * Initializing the Stripe client
 * 
 * For this service to work, you need to install the Stripe PHP SDK via Composer:
 * composer require stripe/stripe-php
 * 
 * Or download manually and connect:
 * require_once 'path/to/stripe-php/init.php';
 */
function getStripeClient() {
    // Checking if Stripe SDK is installed
    if (class_exists('\Stripe\Stripe')) {
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        return new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }
    
    // If Stripe SDK is not installed, use direct API calls
    // This is less convenient, but works without Composer
    return null;
}

/**
 * Creating a Payment Intent for a booking
 * 
 * @param array $booking Booking data
 * @return array Result of creating Payment Intent
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
        
        // Convert the amount to cents (Stripe uses cents)
        $amountInCents = intval(round($amount * 100));
        
        // Creating Payment Intent via Stripe API
        $stripeClient = getStripeClient();
        
        if ($stripeClient) {
            // We use Stripe SDK (if installed)
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
            // We use direct API calls (without SDK)
            $paymentIntentId = createPaymentIntentViaAPI($amountInCents, $currency, $bookingId, $booking, $email);
            $clientSecret = getPaymentIntentClientSecret($paymentIntentId);
        }
        
        // Updating a reservation with Payment Intent ID
        if ($bookingId) {
            updateRecord($conn, 'bookings', [
                'payment_intent_id' => $paymentIntentId,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$bookingId]);
        }
        
        // Logging the creation of Payment Intent
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
 * Creating Payment Intent via direct API call (no SDK)
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
 * Obtaining Client Secret for Payment Intent
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
 * Processing a successful payment
 * 
 * @param string $paymentIntentId ID Payment Intent
 * @return array Processing result
 */
function processSuccessfulPayment($paymentIntentId) {
    global $conn;
    
    try {
        // Getting information about Payment Intent
        $paymentIntent = retrievePaymentIntent($paymentIntentId);
        
        if (!$paymentIntent) {
            throw new Exception('Payment Intent not found');
        }
        
        $bookingId = $paymentIntent['metadata']['booking_id'] ?? null;
        
        if (!$bookingId) {
            throw new Exception('Booking ID not found in payment metadata');
        }
        
        // Updating the reservation payment status
        $updateData = [
            'payment_status' => 'paid',
            'stripe_payment_id' => $paymentIntentId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        if (!$result) {
            throw new Exception('Failed to update booking payment status');
        }
        
        // Logging successful payment
        logActivity("Payment successful: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
        
        // We receive an updated reservation
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
 * Getting information about Payment Intent
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
 * Processing refunds for cancellations
 * 
 * @param int $bookingId Booking ID
 * @param float $amount Refund amount (optional, default full refund)
 * @return array Return result
 */
function refundPayment($bookingId, $amount = null) {
    global $conn;
    
    try {
        // We receive a reservation
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
        
        // Determining the refund amount
        $refundAmount = $amount !== null ? floatval($amount) : floatval($booking['total_amount']);
        $refundAmountInCents = intval(round($refundAmount * 100));
        
        // Creating a refund via Stripe API
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
        
        // Updating the reservation payment status
        $updateData = [
            'payment_status' => 'refunded',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
        
        // Logging the return
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
 * Checking payment status
 * 
 * @param string $paymentIntentId ID Payment Intent
 * @return array Payment status
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
            'amount' => $paymentIntent['amount'] / 100, // Convert from cents
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



