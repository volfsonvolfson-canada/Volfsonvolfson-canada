<?php
/**
 * Stripe Webhook Handler
 * Stripe webhook event handler
 * 
 * Setting up a webhook in Stripe Dashboard:
 * 1. Developers → Webhooks → Add endpoint
 * 2. Endpoint URL: https://backtobase.ca/stripe_webhook.php
 * 3. Events to send: payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled
 * 4. Copy Signing secret and add to config.php as STRIPE_WEBHOOK_SECRET
 */

require_once 'config.php';
require_once 'common.php';
require_once 'payment_service.php';

// We connect the email service if Mailgun is configured
if (!empty(MAILGUN_API_KEY)) {
    require_once 'email_service.php';
}

// We get raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sig_header)) {
    http_response_code(400);
    error_log("Stripe webhook: Missing payload or signature");
    exit;
}

// Checking the webhook signature
if (!empty(STRIPE_WEBHOOK_SECRET)) {
    try {
        verifyWebhookSignature($payload, $sig_header, STRIPE_WEBHOOK_SECRET);
    } catch (Exception $e) {
        http_response_code(400);
        error_log("Stripe webhook signature verification failed: " . $e->getMessage());
        exit;
    }
} else {
    // If webhook secret is not configured, log the warning but continue
    error_log("Warning: STRIPE_WEBHOOK_SECRET is not configured. Webhook signature verification skipped.");
}

// Decoding JSON payload
$event = json_decode($payload, true);

if (!$event) {
    http_response_code(400);
    error_log("Stripe webhook: Invalid JSON payload");
    exit;
}

// Logging the event
logActivity("Stripe webhook received: {$event['type']}, ID: {$event['id']}");

// Processing the event
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
            // Logging an unknown event but not returning an error
            logActivity("Stripe webhook: Unknown event type: {$eventType}", 'INFO');
            break;
    }
    
    // Returning a successful response to Stripe
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Exception $e) {
    // Log the error but return a successful response to Stripe
    // (so Stripe doesn't repeat the event endlessly)
    error_log("Stripe webhook processing error: " . $e->getMessage());
    logActivity("Stripe webhook processing error: " . $e->getMessage(), 'ERROR');
    
    http_response_code(200);
    echo json_encode(['received' => true, 'error' => $e->getMessage()]);
}

/**
 * Webhook signature verification
 */
function verifyWebhookSignature($payload, $sig_header, $secret) {
    // If Stripe SDK is installed, use it to check
    if (class_exists('\Stripe\Webhook')) {
        try {
            \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
            return true;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    // Otherwise, check manually (simplified version)
    // For production it is recommended to use Stripe SDK
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
 * Processing a successful payment
 */
function handlePaymentIntentSucceeded($paymentIntent) {
    global $conn;

    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';

        $result = processSuccessfulPayment($paymentIntentId);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to process payment');
        }

        if (function_exists('btb_dispatch_payment_success_emails')) {
            btb_dispatch_payment_success_emails($result, $paymentIntentId, is_array($paymentIntent) ? $paymentIntent : []);
        }

        if (($result['kind'] ?? '') === 'combined') {
            $logRef = 'combined rooms=' . count($result['room_bookings'] ?? []) . ' wellness=' . count($result['massage_bookings'] ?? []);
        } else {
            $logRef = ($result['kind'] ?? '') === 'massage'
                ? ('Wellness ' . ($result['massage_booking_id'] ?? ''))
                : ('Booking ' . ($result['booking_id'] ?? ''));
        }
        logActivity("Payment intent succeeded processed: Payment Intent {$paymentIntentId}, {$logRef}");
    } catch (Exception $e) {
        error_log('Error handling payment_intent.succeeded: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Processing a failed payment
 */
function handlePaymentIntentFailed($paymentIntent) {
    global $conn;

    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';
        $meta = $paymentIntent['metadata'] ?? [];
        $kind = isset($meta['booking_kind']) ? (string) $meta['booking_kind'] : 'room';

        if ($kind === 'combined') {
            $roomsRaw = isset($meta['combined_rooms']) ? trim((string) $meta['combined_rooms']) : '';
            $massRaw = isset($meta['combined_massages']) ? trim((string) $meta['combined_massages']) : '';
            foreach ($roomsRaw !== '' ? explode(',', $roomsRaw) : [] as $p) {
                $bid = intval(trim($p));
                if ($bid <= 0) {
                    continue;
                }
                updateRecord($conn, 'bookings', [
                    'payment_status' => 'failed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$bid]);
            }
            foreach ($massRaw !== '' ? explode(',', $massRaw) : [] as $p) {
                $mid = intval(trim($p));
                if ($mid <= 0) {
                    continue;
                }
                if (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'payment_status')) {
                    $u = ['payment_status' => 'failed'];
                    if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                        $u['updated_at'] = date('Y-m-d H:i:s');
                    }
                    updateRecord($conn, 'massage_bookings', $u, 'id = ?', [$mid]);
                }
            }
            logActivity("Payment intent failed (combined): Payment Intent {$paymentIntentId}");
            return;
        }

        if ($kind === 'massage') {
            $mid = isset($meta['massage_booking_id']) ? (int) $meta['massage_booking_id'] : 0;
            if ($mid <= 0) {
                return;
            }
            if (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'payment_status')) {
                $u = ['payment_status' => 'failed'];
                if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                    $u['updated_at'] = date('Y-m-d H:i:s');
                }
                updateRecord($conn, 'massage_bookings', $u, 'id = ?', [$mid]);
            }
            logActivity("Payment intent failed (wellness): Payment Intent {$paymentIntentId}, Massage booking {$mid}");
            return;
        }

        $bookingId = $meta['booking_id'] ?? null;
        if (!$bookingId) {
            return;
        }

        updateRecord($conn, 'bookings', [
            'payment_status' => 'failed',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$bookingId]);

        logActivity("Payment intent failed: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
    } catch (Exception $e) {
        error_log('Error handling payment_intent.payment_failed: ' . $e->getMessage());
    }
}

/**
 * Processing a canceled payment
 */
function handlePaymentIntentCanceled($paymentIntent) {
    global $conn;

    try {
        $paymentIntentId = $paymentIntent['id'] ?? '';
        $meta = $paymentIntent['metadata'] ?? [];
        $kind = isset($meta['booking_kind']) ? (string) $meta['booking_kind'] : 'room';

        if ($kind === 'combined') {
            $roomsRaw = isset($meta['combined_rooms']) ? trim((string) $meta['combined_rooms']) : '';
            $massRaw = isset($meta['combined_massages']) ? trim((string) $meta['combined_massages']) : '';
            foreach ($roomsRaw !== '' ? explode(',', $roomsRaw) : [] as $p) {
                $bid = intval(trim($p));
                if ($bid <= 0) {
                    continue;
                }
                updateRecord($conn, 'bookings', [
                    'payment_status' => 'pending',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$bid]);
            }
            foreach ($massRaw !== '' ? explode(',', $massRaw) : [] as $p) {
                $mid = intval(trim($p));
                if ($mid <= 0) {
                    continue;
                }
                if (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'payment_status')) {
                    $u = ['payment_status' => 'pending'];
                    if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                        $u['updated_at'] = date('Y-m-d H:i:s');
                    }
                    updateRecord($conn, 'massage_bookings', $u, 'id = ?', [$mid]);
                }
            }
            logActivity("Payment intent canceled (combined): Payment Intent {$paymentIntentId}");
            return;
        }

        if ($kind === 'massage') {
            $mid = isset($meta['massage_booking_id']) ? (int) $meta['massage_booking_id'] : 0;
            if ($mid <= 0) {
                return;
            }
            if (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'payment_status')) {
                $u = ['payment_status' => 'pending'];
                if (btb_massage_bookings_column_exists($conn, 'updated_at')) {
                    $u['updated_at'] = date('Y-m-d H:i:s');
                }
                updateRecord($conn, 'massage_bookings', $u, 'id = ?', [$mid]);
            }
            logActivity("Payment intent canceled (wellness): Payment Intent {$paymentIntentId}, Massage booking {$mid}");
            return;
        }

        $bookingId = $meta['booking_id'] ?? null;
        if (!$bookingId) {
            return;
        }

        updateRecord($conn, 'bookings', [
            'payment_status' => 'pending',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$bookingId]);

        logActivity("Payment intent canceled: Payment Intent {$paymentIntentId}, Booking {$bookingId}");
    } catch (Exception $e) {
        error_log('Error handling payment_intent.canceled: ' . $e->getMessage());
    }
}

?>

