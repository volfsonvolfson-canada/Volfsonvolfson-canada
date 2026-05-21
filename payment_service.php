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
 * Whether massage_bookings has a given column (for optional Stripe fields).
 */
/** Whether a booking row is already marked paid (room or wellness). */
function btb_row_payment_is_paid(?array $row): bool
{
    if (!$row) {
        return false;
    }

    return strtolower(trim((string) ($row['payment_status'] ?? ''))) === 'paid';
}

function btb_massage_bookings_column_exists($conn, string $col): bool
{
    static $cache = [];
    if (!array_key_exists($col, $cache)) {
        $e = $conn->real_escape_string($col);
        $r = @$conn->query("SHOW COLUMNS FROM massage_bookings LIKE '{$e}'");
        $cache[$col] = $r && $r->num_rows > 0;
    }
    return $cache[$col];
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
        $taxable = floatval($booking['total_amount'] ?? 0);
        $amount = function_exists('btb_grand_total_with_taxes')
            ? btb_grand_total_with_taxes($conn, $taxable)
            : $taxable;
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
                    'booking_kind' => 'room',
                    'booking_id' => (string) $bookingId,
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
        'metadata[booking_kind]' => 'room',
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
 * Create Stripe Payment Intent for a wellness (massage_bookings) row — direct API (no SDK).
 */
function createMassagePaymentIntentViaAPI($amountInCents, $currency, $massageBookingId, array $mb, $email) {
    $url = 'https://api.stripe.com/v1/payment_intents';
    $data = [
        'amount' => $amountInCents,
        'currency' => $currency,
        'metadata[booking_kind]' => 'massage',
        'metadata[massage_booking_id]' => $massageBookingId,
        'metadata[massage_type]' => $mb['massage_type'] ?? '',
        'metadata[massage_date]' => $mb['massage_date'] ?? '',
        'metadata[massage_time]' => $mb['massage_time'] ?? '',
        'metadata[guest_name]' => $mb['guest_name'] ?? '',
        'receipt_email' => $email,
        'description' => "Wellness booking #{$massageBookingId} - " . ($mb['massage_type'] ?? 'Wellness'),
        'automatic_payment_methods[enabled]' => 'true',
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded',
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
 * Create Stripe Payment Intent for wellness booking (massage_bookings).
 */
function createMassagePaymentIntent(array $mb) {
    global $conn;
    try {
        if (!btb_massage_bookings_column_exists($conn, 'payment_intent_id')) {
            throw new Exception('massage_bookings.payment_intent_id missing — run ensure_massage_bookings_payment_columns.php');
        }
        $bookingId = (int) ($mb['id'] ?? 0);
        $amount = floatval($mb['total_amount'] ?? 0);
        $currency = strtolower($mb['currency'] ?? STRIPE_CURRENCY);
        $email = $mb['email'] ?? '';
        if ($bookingId <= 0) {
            throw new Exception('Invalid massage booking ID');
        }
        if ($amount <= 0) {
            throw new Exception('Invalid payment amount');
        }
        $amountInCents = intval(round($amount * 100));
        $stripeClient = getStripeClient();
        if ($stripeClient) {
            $paymentIntent = $stripeClient->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'booking_kind' => 'massage',
                    'massage_booking_id' => (string) $bookingId,
                    'massage_type' => $mb['massage_type'] ?? '',
                    'massage_date' => $mb['massage_date'] ?? '',
                    'massage_time' => $mb['massage_time'] ?? '',
                    'guest_name' => $mb['guest_name'] ?? '',
                ],
                'receipt_email' => $email,
                'description' => "Wellness booking #{$bookingId} - " . ($mb['massage_type'] ?? 'Wellness'),
                'automatic_payment_methods' => ['enabled' => true],
            ]);
            $paymentIntentId = $paymentIntent->id;
            $clientSecret = $paymentIntent->client_secret;
        } else {
            $paymentIntentId = createMassagePaymentIntentViaAPI($amountInCents, $currency, $bookingId, $mb, $email);
            $clientSecret = getPaymentIntentClientSecret($paymentIntentId);
        }
        $update = ['payment_intent_id' => $paymentIntentId];
        if (btb_massage_bookings_column_exists($conn, 'payment_status')) {
            $update['payment_status'] = 'pending';
        }
        if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
            $update['updated_at'] = date('Y-m-d H:i:s');
        }
        updateRecord($conn, 'massage_bookings', $update, 'id = ?', [$bookingId]);
        logActivity("Massage Payment Intent created: {$paymentIntentId}, Wellness booking: {$bookingId}, Amount: {$amount} {$currency}");
        return [
            'success' => true,
            'payment_intent_id' => $paymentIntentId,
            'client_secret' => $clientSecret,
            'amount' => $amount,
            'currency' => strtoupper($currency),
        ];
    } catch (Exception $e) {
        logActivity('Create Massage Payment Intent error: ' . $e->getMessage(), 'ERROR');
        error_log('Massage Payment Intent creation failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
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
 * @return array Processing result with kind "room"|"massage" and row for emails
 */
function processSuccessfulPayment($paymentIntentId) {
    global $conn;

    try {
        $paymentIntent = retrievePaymentIntent($paymentIntentId);

        if (!$paymentIntent) {
            throw new Exception('Payment Intent not found');
        }

        $meta = $paymentIntent['metadata'] ?? [];
        $kind = isset($meta['booking_kind']) ? (string) $meta['booking_kind'] : 'room';

        if ($kind === 'combined') {
            $roomsRaw = isset($meta['combined_rooms']) ? trim((string) $meta['combined_rooms']) : '';
            $massRaw = isset($meta['combined_massages']) ? trim((string) $meta['combined_massages']) : '';
            $roomIds = [];
            if ($roomsRaw !== '') {
                foreach (explode(',', $roomsRaw) as $p) {
                    $n = intval(trim($p));
                    if ($n > 0) {
                        $roomIds[] = $n;
                    }
                }
            }
            $roomIds = array_values(array_unique($roomIds));
            $massageIds = [];
            if ($massRaw !== '') {
                foreach (explode(',', $massRaw) as $p) {
                    $n = intval(trim($p));
                    if ($n > 0) {
                        $massageIds[] = $n;
                    }
                }
            }
            $massageIds = array_values(array_unique($massageIds));

            if (count($roomIds) === 0 && count($massageIds) === 0) {
                throw new Exception('combined booking ids missing in payment metadata');
            }

            $roomBookingsOut = [];
            $sendSuccessEmails = false;
            foreach ($roomIds as $bookingId) {
                $before = fetchOne($conn, 'SELECT * FROM bookings WHERE id = ?', [$bookingId]);
                if ($before && !btb_row_payment_is_paid($before)) {
                    $sendSuccessEmails = true;
                }
                $updateData = [
                    'payment_status' => 'paid',
                    'stripe_payment_id' => $paymentIntentId,
                    'payment_intent_id' => $paymentIntentId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);
                if (!$result) {
                    throw new Exception('Failed to update room booking payment status (combined)');
                }
                $booking = fetchOne($conn, 'SELECT * FROM bookings WHERE id = ?', [$bookingId]);
                if ($booking) {
                    $roomBookingsOut[] = $booking;
                }
                logActivity("Payment successful (combined room): Payment Intent {$paymentIntentId}, Booking {$bookingId}");
            }

            $massageBookingsOut = [];
            foreach ($massageIds as $massageId) {
                $before = fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ?', [$massageId]);
                if ($before && !btb_row_payment_is_paid($before)) {
                    $sendSuccessEmails = true;
                }
                $updateData = [
                    'stripe_payment_id' => $paymentIntentId,
                    'payment_intent_id' => $paymentIntentId,
                ];
                if (btb_massage_bookings_column_exists($conn, 'payment_status')) {
                    $updateData['payment_status'] = 'paid';
                }
                if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                }
                $result = updateRecord($conn, 'massage_bookings', $updateData, 'id = ?', [$massageId]);
                if (!$result) {
                    throw new Exception('Failed to update wellness booking payment status (combined)');
                }
                $mb = fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ?', [$massageId]);
                if ($mb) {
                    $massageBookingsOut[] = $mb;
                }
                logActivity("Payment successful (combined wellness): Payment Intent {$paymentIntentId}, Massage booking {$massageId}");
            }

            return [
                'success' => true,
                'kind' => 'combined',
                'payment_intent_id' => $paymentIntentId,
                'room_bookings' => $roomBookingsOut,
                'massage_bookings' => $massageBookingsOut,
                'send_success_emails' => $sendSuccessEmails,
            ];
        }

        if ($kind === 'massage') {
            $massageId = isset($meta['massage_booking_id']) ? (int) $meta['massage_booking_id'] : 0;
            if ($massageId <= 0) {
                throw new Exception('massage_booking_id not found in payment metadata');
            }
            $before = fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ?', [$massageId]);
            $sendSuccessEmails = $before && !btb_row_payment_is_paid($before);
            $updateData = [
                'stripe_payment_id' => $paymentIntentId,
            ];
            if (btb_massage_bookings_column_exists($conn, 'payment_status')) {
                $updateData['payment_status'] = 'paid';
            }
            if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
            }
            $result = updateRecord($conn, 'massage_bookings', $updateData, 'id = ?', [$massageId]);
            if (!$result) {
                throw new Exception('Failed to update wellness booking payment status');
            }
            logActivity("Payment successful (wellness): Payment Intent {$paymentIntentId}, Massage booking {$massageId}");
            $mb = fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ?', [$massageId]);
            return [
                'success' => true,
                'kind' => 'massage',
                'massage_booking_id' => $massageId,
                'payment_intent_id' => $paymentIntentId,
                'massage_booking' => $mb,
                'send_success_emails' => $sendSuccessEmails,
            ];
        }

        $bookingId = isset($meta['booking_id']) ? (int) $meta['booking_id'] : 0;
        if ($bookingId <= 0) {
            throw new Exception('Booking ID not found in payment metadata');
        }

        $before = fetchOne($conn, 'SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        $sendSuccessEmails = $before && !btb_row_payment_is_paid($before);

        $updateData = [
            'payment_status' => 'paid',
            'stripe_payment_id' => $paymentIntentId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $result = updateRecord($conn, 'bookings', $updateData, 'id = ?', [$bookingId]);

        if (!$result) {
            throw new Exception('Failed to update booking payment status');
        }

        logActivity("Payment successful: Payment Intent {$paymentIntentId}, Booking {$bookingId}");

        $booking = fetchOne($conn, 'SELECT * FROM bookings WHERE id = ?', [$bookingId]);

        return [
            'success' => true,
            'kind' => 'room',
            'booking_id' => $bookingId,
            'payment_intent_id' => $paymentIntentId,
            'booking' => $booking,
            'send_success_emails' => $sendSuccessEmails,
        ];
    } catch (Exception $e) {
        logActivity('Process payment error: ' . $e->getMessage(), 'ERROR');
        error_log('Payment processing failed: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
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

/**
 * Reuse an existing incomplete Stripe Payment Intent for a room booking when possible; otherwise create a new one.
 *
 * @param array $booking Row from bookings (must include id, total_amount, currency, …)
 * @return array Same shape as createPaymentIntent()
 */
function ensureRoomBookingPaymentIntent(array $booking): array
{
    global $conn;
    $existingPi = trim((string) ($booking['payment_intent_id'] ?? ''));
    $taxable = floatval($booking['total_amount'] ?? 0);
    $expectedGrand = function_exists('btb_grand_total_with_taxes')
        ? btb_grand_total_with_taxes($conn, $taxable)
        : $taxable;
    $expectedCents = intval(round($expectedGrand * 100));
    if ($existingPi !== '') {
        $pi = retrievePaymentIntent($existingPi);
        if (is_array($pi)) {
            $st = (string) ($pi['status'] ?? '');
            if (in_array($st, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                $piCents = (int) ($pi['amount'] ?? 0);
                if ($piCents === $expectedCents) {
                    try {
                        $secret = getPaymentIntentClientSecret($existingPi);
                        $cur = strtolower((string) ($booking['currency'] ?? STRIPE_CURRENCY));

                        return [
                            'success' => true,
                            'payment_intent_id' => $existingPi,
                            'client_secret' => $secret,
                            'amount' => $expectedGrand,
                            'currency' => strtoupper($cur),
                        ];
                    } catch (Exception $e) {
                        logActivity('ensureRoomBookingPaymentIntent: could not reuse PI, creating new: ' . $e->getMessage(), 'INFO');
                    }
                } else {
                    btb_stripe_cancel_open_payment_intent($existingPi);
                }
            }
        }
    }

    return createPaymentIntent($booking);
}

/**
 * Reuse an existing incomplete Stripe Payment Intent for a wellness booking when possible; otherwise create a new one.
 *
 * @param array $mb massage_bookings row
 * @return array Same shape as createMassagePaymentIntent()
 */
function ensureMassageBookingPaymentIntent(array $mb): array
{
    global $conn;
    $existingPi = trim((string) ($mb['payment_intent_id'] ?? ''));
    $expectedGrand = floatval($mb['total_amount'] ?? 0);
    $expectedCents = intval(round($expectedGrand * 100));
    if ($existingPi !== '') {
        $pi = retrievePaymentIntent($existingPi);
        if (is_array($pi)) {
            $st = (string) ($pi['status'] ?? '');
            if (in_array($st, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                $piCents = (int) ($pi['amount'] ?? 0);
                if ($piCents === $expectedCents) {
                    try {
                        $secret = getPaymentIntentClientSecret($existingPi);
                        $cur = strtolower((string) ($mb['currency'] ?? STRIPE_CURRENCY));

                        return [
                            'success' => true,
                            'payment_intent_id' => $existingPi,
                            'client_secret' => $secret,
                            'amount' => $expectedGrand,
                            'currency' => strtoupper($cur),
                        ];
                    } catch (Exception $e) {
                        logActivity('ensureMassageBookingPaymentIntent: could not reuse PI, creating new: ' . $e->getMessage(), 'INFO');
                    }
                } else {
                    btb_stripe_cancel_open_payment_intent($existingPi);
                }
            }
        }
    }

    return createMassagePaymentIntent($mb);
}

/**
 * Recreate Stripe Payment Intent after admin/guest changed booking amount (unpaid confirmed only).
 */
function btb_refresh_room_booking_payment_intent($conn, array $booking): void
{
    if (strtolower(trim((string) ($booking['payment_status'] ?? ''))) === 'paid') {
        return;
    }
    if (($booking['status'] ?? '') !== 'confirmed') {
        return;
    }
    if (empty(STRIPE_SECRET_KEY) || floatval($booking['total_amount'] ?? 0) <= 0) {
        return;
    }
    $piOld = trim((string) ($booking['payment_intent_id'] ?? ''));
    if ($piOld !== '') {
        btb_stripe_cancel_open_payment_intent($piOld);
    }
    try {
        ensureRoomBookingPaymentIntent($booking);
    } catch (Throwable $e) {
        logActivity('btb_refresh_room_booking_payment_intent: ' . $e->getMessage(), 'WARNING');
    }
}

function btb_refresh_massage_booking_payment_intent($conn, array $mb): void
{
    if (function_exists('btb_massage_bookings_column_exists')
        && btb_massage_bookings_column_exists($conn, 'payment_status')
        && strtolower(trim((string) ($mb['payment_status'] ?? ''))) === 'paid') {
        return;
    }
    if (($mb['status'] ?? '') !== 'confirmed') {
        return;
    }
    if (empty(STRIPE_SECRET_KEY) || floatval($mb['total_amount'] ?? 0) <= 0) {
        return;
    }
    $piOld = trim((string) ($mb['payment_intent_id'] ?? ''));
    if ($piOld !== '') {
        btb_stripe_cancel_open_payment_intent($piOld);
    }
    try {
        ensureMassageBookingPaymentIntent($mb);
    } catch (Throwable $e) {
        logActivity('btb_refresh_massage_booking_payment_intent: ' . $e->getMessage(), 'WARNING');
    }
}

/**
 * Cancel an incomplete PaymentIntent so guests are not left with multiple open checkout amounts.
 */
function btb_stripe_cancel_open_payment_intent(string $paymentIntentId): void
{
    $paymentIntentId = trim($paymentIntentId);
    if ($paymentIntentId === '') {
        return;
    }
    $pi = retrievePaymentIntent($paymentIntentId);
    if (!is_array($pi)) {
        return;
    }
    $st = (string) ($pi['status'] ?? '');
    if (!in_array($st, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
        return;
    }
    $url = 'https://api.stripe.com/v1/payment_intents/' . rawurlencode($paymentIntentId) . '/cancel';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * One Stripe PaymentIntent for multiple guest bookings (room + wellness). Amount includes tax % from my_bookings_pricing_settings.
 * Does not write payment_intent_id on booking rows until payment succeeds.
 *
 * @param int $amountCents Total charge in smallest currency unit
 * @param array<int> $roomIds
 * @param array<int> $massageIds
 * @return array{success:bool, payment_intent_id?:string, client_secret?:string, amount?:float, currency?:string, error?:string}
 */
function createCombinedGuestPaymentIntent(int $amountCents, string $currency, string $receiptEmail, array $roomIds, array $massageIds, string $description): array
{
    try {
        if ($amountCents <= 0) {
            throw new Exception('Invalid combined payment amount');
        }
        $currency = strtolower(preg_replace('/[^a-z]/i', '', $currency));
        if ($currency === '') {
            $currency = strtolower(STRIPE_CURRENCY);
        }

        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds), static function ($n) {
            return $n > 0;
        })));
        $massageIds = array_values(array_unique(array_filter(array_map('intval', $massageIds), static function ($n) {
            return $n > 0;
        })));

        $metaBase = [
            'booking_kind' => 'combined',
            'combined_rooms' => implode(',', $roomIds),
            'combined_massages' => implode(',', $massageIds),
            'combined_grand_cents' => (string) $amountCents,
        ];

        $stripeClient = getStripeClient();
        if ($stripeClient) {
            $createPayload = [
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata' => $metaBase,
                'description' => $description !== '' ? $description : 'Combined Back to Base bookings',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ];
            if ($receiptEmail !== '') {
                $createPayload['receipt_email'] = $receiptEmail;
            }
            $paymentIntent = $stripeClient->paymentIntents->create($createPayload);
            $paymentIntentId = $paymentIntent->id;
            $clientSecret = $paymentIntent->client_secret;
        } else {
            $url = 'https://api.stripe.com/v1/payment_intents';
            $data = [
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata[booking_kind]' => 'combined',
                'metadata[combined_rooms]' => $metaBase['combined_rooms'],
                'metadata[combined_massages]' => $metaBase['combined_massages'],
                'metadata[combined_grand_cents]' => $metaBase['combined_grand_cents'],
                'description' => $description !== '' ? $description : 'Combined Back to Base bookings',
                'automatic_payment_methods[enabled]' => 'true',
            ];
            if ($receiptEmail !== '') {
                $data['receipt_email'] = $receiptEmail;
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . STRIPE_SECRET_KEY,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) {
                $error = json_decode((string) $response, true);
                throw new Exception($error['error']['message'] ?? 'Failed to create combined payment intent');
            }
            $result = json_decode((string) $response, true);
            $paymentIntentId = $result['id'] ?? '';
            $clientSecret = getPaymentIntentClientSecret($paymentIntentId);
        }

        logActivity("Combined Payment Intent created: {$paymentIntentId}, rooms=" . implode(',', $roomIds) . ', massages=' . implode(',', $massageIds));

        return [
            'success' => true,
            'payment_intent_id' => $paymentIntentId,
            'client_secret' => $clientSecret,
            'amount' => round($amountCents / 100, 2),
            'currency' => strtoupper($currency),
        ];
    } catch (Exception $e) {
        logActivity('Create combined Payment Intent error: ' . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}



