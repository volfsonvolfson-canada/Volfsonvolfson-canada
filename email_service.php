<?php
/**
 * Email Service
 * Service for sending email via Mailgun
 */

require_once 'config.php';
require_once 'common.php';

/** Queue-backed delivery is on by default (set false in config.php to bypass queue). */
function btb_email_queue_enabled(): bool {
    if (defined('BTB_EMAIL_QUEUE_ENABLED')) {
        return (bool) BTB_EMAIL_QUEUE_ENABLED;
    }
    return true;
}

function btb_ensure_email_delivery_queue_table($conn): bool {
    if (!$conn) {
        return false;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `email_delivery_queue` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `to_email` VARCHAR(320) NOT NULL,
      `subject` VARCHAR(255) NOT NULL,
      `html_body` MEDIUMTEXT NOT NULL,
      `text_body` MEDIUMTEXT NULL,
      `template_key` VARCHAR(80) NOT NULL DEFAULT '',
      `context_json` MEDIUMTEXT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
      `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
      `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
      `provider_message_id` VARCHAR(255) NOT NULL DEFAULT '',
      `provider_status` VARCHAR(100) NOT NULL DEFAULT '',
      `last_error` TEXT NULL,
      `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `sent_at` DATETIME NULL,
      `delivered_at` DATETIME NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY `idx_email_delivery_queue_status_next` (`status`, `next_attempt_at`),
      KEY `idx_email_delivery_queue_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool) @$conn->query($sql);
}

/** Direct Mailgun transport. Used by queue worker and optional sync fallback. */
function btb_send_email_via_mailgun($to, $subject, $htmlContent, $textContent = ''): array {
    if (empty(MAILGUN_API_KEY)) {
        return ['success' => false, 'error' => 'Mailgun API key is not configured'];
    }
    if (empty(MAILGUN_DOMAIN)) {
        return ['success' => false, 'error' => 'Mailgun domain is not configured'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    if (function_exists('btb_email_finalize_outbound_html')) {
        global $conn;
        $htmlContent = btb_email_finalize_outbound_html($htmlContent, $conn ?? null);
    }
    if (empty($textContent)) {
        $textContent = strip_tags($htmlContent);
    }
    $url = 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages';
    $data = [
        'from' => MAILGUN_FROM_NAME . ' <' . MAILGUN_FROM_EMAIL . '>',
        'to' => $to,
        'subject' => $subject,
        'text' => $textContent,
        'html' => $htmlContent
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
    curl_setopt($ch, CURLOPT_TIMEOUT, 28);
    curl_setopt($ch, CURLOPT_USERPWD, 'api:' . MAILGUN_API_KEY);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) {
        return ['success' => false, 'error' => "cURL error: {$curlError}", 'http_code' => $httpCode, 'response' => (string) $response];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode((string) $response, true);
        $messageId = $responseData['id'] ?? 'unknown';
        return ['success' => true, 'message' => 'Email sent successfully', 'message_id' => $messageId, 'http_code' => $httpCode];
    }
    $error = json_decode((string) $response, true);
    $errorMessage = $error['message'] ?? ($response ? substr((string) $response, 0, 300) : 'Unknown error');
    return ['success' => false, 'error' => "Mailgun API error ({$httpCode}): {$errorMessage}", 'http_code' => $httpCode, 'response' => (string) $response];
}

/** Enqueue outbound email for async worker. */
function btb_email_enqueue($to, $subject, $htmlContent, $textContent = '', array $meta = []): array {
    global $conn;
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return ['success' => false, 'error' => 'Email queue table unavailable'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    $templateKey = trim((string) ($meta['template_key'] ?? ''));
    if (function_exists('mb_substr')) {
        $templateKey = mb_substr($templateKey, 0, 80, 'UTF-8');
    } else {
        $templateKey = substr($templateKey, 0, 80);
    }
    $contextJson = '';
    if (isset($meta['context']) && is_array($meta['context'])) {
        $enc = json_encode($meta['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $contextJson = is_string($enc) ? $enc : '';
    }
    $stmt = $conn->prepare(
        'INSERT INTO `email_delivery_queue`
        (`to_email`, `subject`, `html_body`, `text_body`, `template_key`, `context_json`, `status`, `next_attempt_at`)
        VALUES (?, ?, ?, ?, ?, ?, "queued", NOW())'
    );
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error ?: 'Prepare failed'];
    }
    $stmt->bind_param('ssssss', $to, $subject, $htmlContent, $textContent, $templateKey, $contextJson);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Queue insert failed';
        $stmt->close();
        return ['success' => false, 'error' => $err];
    }
    $queueId = (int) $stmt->insert_id;
    $stmt->close();
    logActivity("Email queued: ID {$queueId}, To {$to}, Subject: {$subject}");
    return ['success' => true, 'queued' => true, 'queue_id' => $queueId];
}

/**
 * Sending email via queue (default) or direct Mailgun.
 *
 * @param array<string,mixed> $options ['force_send'=>bool,'template_key'=>string,'context'=>array]
 */
function sendEmail($to, $subject, $htmlContent, $textContent = '', array $options = []) {
    try {
        $forceSend = !empty($options['force_send']);
        if (!$forceSend && btb_email_queue_enabled()) {
            $q = btb_email_enqueue($to, $subject, $htmlContent, $textContent, $options);
            if ($q['success']) {
                return $q;
            }
            // Fail-open fallback: try direct send so business flow does not drop email.
            error_log('Email enqueue failed; fallback to direct send: ' . ($q['error'] ?? 'unknown'));
        }
        $res = btb_send_email_via_mailgun($to, $subject, $htmlContent, $textContent);
        if (!empty($res['success'])) {
            $messageId = $res['message_id'] ?? 'unknown';
            logActivity("Email sent successfully: To {$to}, Subject: {$subject}, Message ID: {$messageId}");
        } else {
            logActivity("Email send failed: To {$to}, Subject: {$subject}, Error: " . ($res['error'] ?? 'Unknown error'), 'ERROR');
        }
        return $res;
    } catch (Exception $e) {
        error_log("Send email error: " . $e->getMessage());
        logActivity("Email send failed: To {$to}, Subject: {$subject}, Error: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** Monitoring API for admin: aggregate status counters. */
function btb_email_delivery_overview($conn): array {
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return [
            'queued' => 0,
            'sending' => 0,
            'sent' => 0,
            'failed' => 0,
            'delivered' => 0,
            'last_24h_sent' => 0,
            'last_24h_failed' => 0,
        ];
    }
    $row = fetchOne($conn, "SELECT
        SUM(status='queued') AS queued,
        SUM(status='sending') AS sending,
        SUM(status='sent') AS sent,
        SUM(status='failed') AS failed,
        SUM(status='delivered') AS delivered,
        SUM((status IN ('sent','delivered')) AND created_at >= (NOW() - INTERVAL 24 HOUR)) AS last_24h_sent,
        SUM(status='failed' AND created_at >= (NOW() - INTERVAL 24 HOUR)) AS last_24h_failed
      FROM email_delivery_queue");
    $toInt = static function ($v): int { return (int) ($v ?? 0); };
    return [
        'queued' => $toInt($row['queued'] ?? 0),
        'sending' => $toInt($row['sending'] ?? 0),
        'sent' => $toInt($row['sent'] ?? 0),
        'failed' => $toInt($row['failed'] ?? 0),
        'delivered' => $toInt($row['delivered'] ?? 0),
        'last_24h_sent' => $toInt($row['last_24h_sent'] ?? 0),
        'last_24h_failed' => $toInt($row['last_24h_failed'] ?? 0),
    ];
}

/** Monitoring API for admin: recent queue rows. */
function btb_email_delivery_log($conn, int $limit = 50, int $offset = 0): array {
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return ['rows' => [], 'total' => 0];
    }
    $totalRow = fetchOne($conn, 'SELECT COUNT(*) AS c FROM email_delivery_queue');
    $total = (int) ($totalRow['c'] ?? 0);
    $sql = 'SELECT id, to_email, subject, template_key, status, attempts, max_attempts, provider_message_id, provider_status, last_error, next_attempt_at, sent_at, delivered_at, created_at, updated_at
            FROM email_delivery_queue
            ORDER BY id DESC
            LIMIT ? OFFSET ?';
    $rows = fetchAll($conn, $sql, [$limit, $offset]);
    return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
}

/** Admin action: retry one failed row (or all failed when id <= 0). */
function btb_email_delivery_retry($conn, int $id = 0): array {
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return ['success' => false, 'error' => 'Queue unavailable'];
    }
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE email_delivery_queue
            SET status='queued', next_attempt_at=NOW(), last_error=NULL, provider_status=''
            WHERE id=? AND status='failed'");
        if (!$stmt) {
            return ['success' => false, 'error' => $conn->error ?: 'Prepare failed'];
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = (int) $stmt->affected_rows;
        $stmt->close();
        return ['success' => true, 'affected' => $affected];
    }
    $result = executeQuery($conn, "UPDATE email_delivery_queue
        SET status='queued', next_attempt_at=NOW(), last_error=NULL, provider_status=''
        WHERE status='failed'");
    return ['success' => $result !== false, 'affected' => (int) ($result ?: 0)];
}

/** Normalize Mailgun / SMTP Message-Id for comparison (strip brackets, lowercase). */
function btb_normalize_mailgun_message_id(string $id): string
{
    $id = trim($id);
    $id = trim($id, '<>');
    return strtolower($id);
}

/**
 * Verify Mailgun webhook signature (timestamp + token HMAC-SHA256 with signing key).
 *
 * @see https://documentation.mailgun.com/docs/mailgun/api-reference/send/mailgun/event-tracking/#securing-webhooks
 */
function btb_mailgun_verify_webhook_signature(string $timestamp, string $token, string $signature, string $signingKey): bool
{
    $signingKey = trim($signingKey);
    if ($signingKey === '') {
        return false;
    }
    $mac = hash_hmac('sha256', $timestamp . $token, $signingKey);
    return hash_equals($mac, $signature);
}

/**
 * Extract SMTP Message-Id from Mailgun event-data payload (shape varies slightly by event).
 *
 * @param array<string,mixed> $event
 */
function btb_mailgun_event_extract_message_id(array $event): string
{
    $headers = $event['message']['headers'] ?? null;
    if (is_array($headers)) {
        foreach (['message-id', 'Message-Id', 'Message-ID'] as $hk) {
            if (!empty($headers[$hk])) {
                return trim((string) $headers[$hk]);
            }
        }
    }
    foreach (['message-id', 'Message-Id'] as $k) {
        if (!empty($event[$k])) {
            return trim((string) $event[$k]);
        }
    }

    return '';
}

/**
 * Find email_delivery_queue.id by Mailgun message id stored in provider_message_id.
 */
function btb_email_queue_find_id_by_message_id($conn, string $messageIdHeader): ?int
{
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return null;
    }
    $norm = btb_normalize_mailgun_message_id($messageIdHeader);
    if ($norm === '') {
        return null;
    }
    $row = fetchOne(
        $conn,
        "SELECT `id` FROM `email_delivery_queue`
         WHERE `provider_message_id` <> ''
           AND LOWER(REPLACE(REPLACE(TRIM(`provider_message_id`), '<', ''), '>', '')) = ?
         LIMIT 1",
        [$norm]
    );
    if (is_array($row) && isset($row['id'])) {
        return (int) $row['id'];
    }

    return null;
}

/**
 * Apply one Mailgun Events webhook payload (event-data object).
 *
 * @param array<string,mixed> $event
 * @return array{matched: bool, event: string, action: string}
 */
function btb_email_queue_apply_mailgun_webhook_event($conn, array $event): array
{
    $out = ['matched' => false, 'event' => '', 'action' => 'noop'];
    if (!$conn || !btb_ensure_email_delivery_queue_table($conn)) {
        return $out;
    }
    $eventName = strtolower(trim((string) ($event['event'] ?? '')));
    $out['event'] = $eventName;

    $mid = btb_mailgun_event_extract_message_id($event);
    if ($mid === '') {
        return $out;
    }
    $queueId = btb_email_queue_find_id_by_message_id($conn, $mid);
    if ($queueId === null || $queueId <= 0) {
        return $out;
    }
    $out['matched'] = true;

    $deliveryStatus = $event['delivery-status'] ?? null;
    $bounceMsg = '';
    if (is_array($deliveryStatus)) {
        $bounceMsg = trim((string) ($deliveryStatus['message'] ?? ''));
    }
    $severity = strtolower(trim((string) ($event['severity'] ?? '')));
    if ($bounceMsg === '' && isset($event['reason'])) {
        $bounceMsg = trim((string) $event['reason']);
    }

    switch ($eventName) {
        case 'delivered':
            $stmt = executeQuery(
                $conn,
                "UPDATE `email_delivery_queue`
                 SET `status` = 'delivered',
                     `delivered_at` = COALESCE(`delivered_at`, NOW()),
                     `provider_status` = 'delivered',
                     `updated_at` = NOW()
                 WHERE `id` = ?
                   AND `status` IN ('sent', 'delivered')",
                [(string) $queueId]
            );
            if ($stmt) {
                $stmt->close();
            }
            $out['action'] = 'delivered';
            if (function_exists('logActivity')) {
                logActivity("Mailgun webhook: delivered queue_id={$queueId} msg={$mid}", 'INFO');
            }
            break;

        case 'opened':
            $stmt = executeQuery(
                $conn,
                "UPDATE `email_delivery_queue`
                 SET `provider_status` = CASE
                       WHEN `status` = 'delivered' THEN 'delivered_opened'
                       ELSE 'opened'
                     END,
                     `updated_at` = NOW()
                 WHERE `id` = ?
                   AND `status` IN ('sent', 'delivered')",
                [(string) $queueId]
            );
            if ($stmt) {
                $stmt->close();
            }
            $out['action'] = 'opened';
            break;

        case 'clicked':
            $stmt = executeQuery(
                $conn,
                "UPDATE `email_delivery_queue`
                 SET `provider_status` = CASE
                       WHEN `status` = 'delivered' OR `provider_status` LIKE 'delivered%' THEN 'delivered_clicked'
                       ELSE 'clicked'
                     END,
                     `updated_at` = NOW()
                 WHERE `id` = ?
                   AND `status` IN ('sent', 'delivered')",
                [(string) $queueId]
            );
            if ($stmt) {
                $stmt->close();
            }
            $out['action'] = 'clicked';
            break;

        case 'failed':
        case 'permanent_fail':
        case 'rejected':
            $isPermanent = ($eventName === 'permanent_fail')
                || ($eventName === 'rejected')
                || ($severity === 'permanent');
            if ($isPermanent) {
                $err = $bounceMsg !== '' ? $bounceMsg : 'Permanent failure (Mailgun)';
                $stmt = executeQuery(
                    $conn,
                    "UPDATE `email_delivery_queue`
                     SET `status` = 'failed',
                         `provider_status` = 'permanent_fail',
                         `last_error` = ?,
                         `updated_at` = NOW()
                     WHERE `id` = ?
                       AND `status` IN ('sent', 'delivered')",
                    [$err, (string) $queueId]
                );
                if ($stmt) {
                    $stmt->close();
                }
                $out['action'] = 'permanent_fail';
            } else {
                $err = $bounceMsg !== '' ? $bounceMsg : 'Temporary failure (Mailgun)';
                $stmt = executeQuery(
                    $conn,
                    "UPDATE `email_delivery_queue`
                     SET `provider_status` = 'temporary_fail',
                         `last_error` = ?,
                         `updated_at` = NOW()
                     WHERE `id` = ?
                       AND `status` IN ('sent', 'delivered')",
                    [$err, (string) $queueId]
                );
                if ($stmt) {
                    $stmt->close();
                }
                $out['action'] = 'temporary_fail';
            }
            break;

        case 'complained':
            $stmt = executeQuery(
                $conn,
                "UPDATE `email_delivery_queue`
                 SET `provider_status` = 'complained',
                     `updated_at` = NOW()
                 WHERE `id` = ?
                       AND `status` IN ('sent', 'delivered')",
                [(string) $queueId]
            );
            if ($stmt) {
                $stmt->close();
            }
            $out['action'] = 'complained';
            break;

        default:
            $out['action'] = 'ignored';
            break;
    }

    return $out;
}

/**
 * Sending a booking confirmation to a guest
 * 
 * @param array $booking Booking data
 * @return array Result of sending
 */
function sendBookingConfirmation($booking) {
    try {
        $email = $booking['email'] ?? '';
        $bookingId = $booking['id'] ?? '';
        $confirmationCode = $booking['confirmation_code'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Uploading a letter template
        $htmlContent = loadEmailTemplate('booking_confirmation', [
            'booking' => $booking,
            'confirmation_code' => $confirmationCode,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($confirmationCode),
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // If the template is not found, use a simple format
            $htmlContent = generateSimpleBookingConfirmationEmail($booking, $confirmationCode);
        }
        
        $subject = 'Booking Confirmation - Back to Base Hotel';
        $ov = btbEmailTemplateApplyAdminOverride('booking_confirmation_guest', [
            'booking' => $booking,
            'confirmation_code' => $confirmationCode,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($confirmationCode),
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        
        // Sending a letter
        $result = sendEmail($email, $ov['subject'], $ov['html']);
        
        // Update the email sending time in the database
        if ($result['success'] && $bookingId) {
            global $conn;
            updateRecord($conn, 'booking_confirmations', 
                ['email_sent_at' => date('Y-m-d H:i:s')], 
                'booking_id = ?', 
                [$bookingId]
            );
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Send booking confirmation error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send notification of a new booking request to the host
 * 
 * @param array $booking Booking data
 * @return array Result of sending
 */
function sendBookingRequestToHost($booking) {
    try {
        $hostEmail = MAILGUN_HOST_EMAIL ?? '';
        
        if (empty($hostEmail)) {
            // If the host email is not configured, just log it
            logActivity("Host email not configured, skipping booking request notification", 'INFO');
            return [
                'success' => true,
                'message' => 'Host email not configured, notification skipped'
            ];
        }
        
        // Uploading a letter template
        $htmlContent = loadEmailTemplate('booking_request', [
            'booking' => $booking,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // If the template is not found, use a simple format
            $htmlContent = generateSimpleBookingRequestEmail($booking);
        }
        
        $subject = 'New Booking Request - Back to Base Hotel';
        $ov = btbEmailTemplateApplyAdminOverride('booking_request_host', [
            'booking' => $booking,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        
        // Sending a letter
        return sendEmail($hostEmail, $ov['subject'], $ov['html']);
        
    } catch (Exception $e) {
        error_log("Send booking request to host error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sending booking confirmation after host approval
 * 
 * @param array $booking Booking data
 * @return array Result of sending
 */
function sendBookingConfirmedToGuest($booking) {
    try {
        $email = $booking['email'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Uploading a letter template
        $htmlContent = loadEmailTemplate('booking_confirmed', [
            'booking' => $booking,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($booking['confirmation_code'] ?? ''),
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // If the template is not found, use a simple format
            $htmlContent = generateSimpleBookingConfirmedEmail($booking);
        }
        
        $subject = 'Your Booking Has Been Confirmed - Back to Base Hotel';
        $ov = btbEmailTemplateApplyAdminOverride('booking_confirmed_guest', [
            'booking' => $booking,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($booking['confirmation_code'] ?? ''),
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        
        // Sending a letter
        return sendEmail($email, $ov['subject'], $ov['html']);
        
    } catch (Exception $e) {
        error_log("Send booking confirmed to guest error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Sending a cancellation notice
 * 
 * @param array $booking Booking data
 * @param string $reason Reason for cancellation (optional)
 * @return array Result of sending
 */
function sendBookingCancelled($booking, $reason = '') {
    try {
        $email = $booking['email'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Guest email is required');
        }
        
        // Uploading a letter template
        $htmlContent = loadEmailTemplate('booking_cancelled', [
            'booking' => $booking,
            'reason' => $reason,
            'site_url' => SITE_URL
        ]);
        
        if (!$htmlContent) {
            // If the template is not found, use a simple format
            $htmlContent = generateSimpleBookingCancelledEmail($booking, $reason);
        }
        
        $subject = 'Booking Cancellation - Back to Base Hotel';
        $ov = btbEmailTemplateApplyAdminOverride('booking_cancelled_guest', [
            'booking' => $booking,
            'reason' => $reason,
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        
        // Sending a letter
        return sendEmail($email, $ov['subject'], $ov['html']);
        
    } catch (Exception $e) {
        error_log("Send booking cancelled error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Uploading an email template
 * 
 * @param string $templateName Template name (no extension)
 * @param array $variables Variables to be substituted into the template
 * @return string|false HTML template contents or false if not found
 */
function loadEmailTemplate($templateName, $variables = []) {
    $templatePath = __DIR__ . '/templates/email_' . $templateName . '.html';
    
    if (!file_exists($templatePath)) {
        return false;
    }
    
    // Reading the template
    $template = file_get_contents($templatePath);
    
    // Replacing variables
    foreach ($variables as $key => $value) {
        if (is_array($value)) {
            // If the value is an array (for example, $booking), replace its fields
            foreach ($value as $subKey => $subValue) {
                $template = str_replace('{{' . $key . '.' . $subKey . '}}', htmlspecialchars($subValue ?? ''), $template);
            }
        } else {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value ?? ''), $template);
        }
    }
    
    return $template;
}

function btbEmailTemplateFlattenVars(array $vars, string $prefix = ''): array
{
    $flat = [];
    foreach ($vars as $k => $v) {
        $key = $prefix === '' ? (string) $k : ($prefix . '.' . (string) $k);
        if (is_array($v)) {
            $flat = array_merge($flat, btbEmailTemplateFlattenVars($v, $key));
        } elseif (is_scalar($v) || $v === null) {
            $flat[$key] = (string) ($v ?? '');
        }
    }
    return $flat;
}

function btbEmailTemplateInterpolate(string $text, array $vars): string
{
    if ($text === '') {
        return '';
    }
    $flat = btbEmailTemplateFlattenVars($vars);
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', static function ($m) use ($flat) {
        $key = (string) ($m[1] ?? '');
        return array_key_exists($key, $flat) ? (string) $flat[$key] : '';
    }, $text);
}

function btbEmailTemplateSafeHref(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return '';
    }
    if (preg_match('/^\s*javascript:/i', $u) || preg_match('/^\s*data:/i', $u)) {
        return '';
    }
    return $u;
}

function btbEmailTemplateRenderFromAdminRow(array $tplRow, array $vars, string $fallbackHtml): string
{
    global $conn;

    $heading = trim((string) ($tplRow['heading'] ?? ''));
    $body = (string) ($tplRow['body'] ?? '');
    $ctaLabel = trim((string) ($tplRow['cta_label'] ?? ''));
    $ctaUrlRaw = (string) ($tplRow['cta_url'] ?? '');
    $imgUrlRaw = (string) ($tplRow['image_url'] ?? '');

    if ($heading === '' && trim($body) === '' && $ctaLabel === '' && trim($ctaUrlRaw) === '' && trim($imgUrlRaw) === '') {
        return $fallbackHtml;
    }

    $br = function_exists('btb_email_branding_api_data') ? btb_email_branding_api_data($conn) : btb_email_branding_defaults();
    $outerHex = btb_email_sanitize_hex_color($br['outer_background'] ?? '', '#f4f4f5');
    $cardHex = btb_email_sanitize_hex_color($br['card_background'] ?? '', '#ffffff');
    $outer = htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8');
    $card = htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8');
    $footerAbs = btb_email_safe_public_url((string) ($br['footer_image_url'] ?? ''));
    $footerAlt = htmlspecialchars(trim((string) ($br['footer_image_alt'] ?? '')), ENT_QUOTES, 'UTF-8');
    $footerSrc = $footerAbs !== '' ? htmlspecialchars($footerAbs, ENT_QUOTES, 'UTF-8') : '';

    $headingR = htmlspecialchars(btbEmailTemplateInterpolate($heading, $vars), ENT_QUOTES, 'UTF-8');
    $bodyR = htmlspecialchars(btbEmailTemplateInterpolate($body, $vars), ENT_QUOTES, 'UTF-8');
    $bodyR = nl2br($bodyR);
    $ctaLabelR = htmlspecialchars(btbEmailTemplateInterpolate($ctaLabel, $vars), ENT_QUOTES, 'UTF-8');
    $ctaUrl = btbEmailTemplateSafeHref(btbEmailTemplateInterpolate($ctaUrlRaw, $vars));
    $imgUrl = btbEmailTemplateSafeHref(btbEmailTemplateInterpolate($imgUrlRaw, $vars));
    if ($imgUrl !== '') {
        $imgUrl = btb_email_safe_public_url($imgUrl);
    }

    $imgBlock = '';
    if ($imgUrl !== '') {
        $imgEsc = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
        $imgBlock = '<div style="margin:0 0 18px;text-align:center;line-height:0;">'
            . '<img src="' . $imgEsc . '" alt="" width="560" style="display:inline-block;max-width:100%;width:100%;height:auto;border:0;border-radius:10px;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" />'
            . '</div>';
    }
    $ctaBlock = '';
    if ($ctaLabelR !== '' && $ctaUrl !== '') {
        $ctaHref = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $ctaBlock = '<p style="margin:22px 0 0;font-family:Arial,Helvetica,sans-serif;">'
            . '<a href="' . $ctaHref . '" style="display:inline-block;padding:12px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;">' . $ctaLabelR . '</a>'
            . '</p>';
    }

    $footerRow = '';
    if ($footerSrc !== '') {
        $footerRow = '<tr><td align="center" width="100%" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="padding:0;line-height:0;font-size:0;background:' . $card . ';text-align:center;">'
            . '<img src="' . $footerSrc . '" alt="' . $footerAlt . '" width="640" style="display:block;margin:0 auto;width:100%;max-width:640px;height:auto;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" />'
            . '</td></tr>';
    }

    $contentInner = $imgBlock
        . ($headingR !== '' ? '<h1 style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.3;color:#111827;">' . $headingR . '</h1>' : '')
        . ($bodyR !== '' ? '<div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . $bodyR . '</div>' : '')
        . $ctaBlock;

    return '<!--btb-email-shell-v2-->'
        . '<!DOCTYPE html><html lang="en"><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="color-scheme" content="light">'
        . '<meta name="supported-color-schemes" content="light">'
        . '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->'
        . '<title></title></head>'
        . '<body bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="margin:0;padding:0;background:' . $outer . ' !important;color:#111827;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;background:' . $outer . ';">'
        . '<tr><td align="center" style="padding:16px 12px 24px 12px;">'
        . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;max-width:640px;width:100%;background:' . $card . ';border-radius:14px;border:1px solid #e5e7eb;overflow:hidden;">'
        . '<tr><td style="padding:22px 22px 22px 22px;background:' . $card . ';">' . $contentInner . '</td></tr>'
        . $footerRow
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}

function btbEmailTemplateApplyAdminOverride(string $templateKey, array $vars, string $fallbackSubject, string $fallbackHtml): array
{
    global $conn;
    if (!function_exists('btb_email_template_get_one')) {
        return ['subject' => $fallbackSubject, 'html' => $fallbackHtml];
    }
    $tpl = btb_email_template_get_one($conn, $templateKey);
    if (!is_array($tpl) || empty($tpl)) {
        return ['subject' => $fallbackSubject, 'html' => $fallbackHtml];
    }
    $subjectRaw = trim((string) ($tpl['subject'] ?? ''));
    $subject = $subjectRaw !== '' ? btbEmailTemplateInterpolate($subjectRaw, $vars) : $fallbackSubject;
    $html = btbEmailTemplateRenderFromAdminRow($tpl, $vars, $fallbackHtml);
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Generating a simple HTML booking confirmation email
 */
function generateSimpleBookingConfirmationEmail($booking, $confirmationCode) {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $totalAmount = $booking['total_amount'] ?? 0;
    $currency = $booking['currency'] ?? 'CAD';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a5568; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .code { font-size: 24px; font-weight: bold; color: #4a5568; text-align: center; padding: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Back to Base Hotel</h1>
            </div>
            <div class='content'>
                <h2>Booking Confirmation</h2>
                <p>Dear {$guestName},</p>
                <p>Thank you for your booking! Your reservation has been received.</p>
                
                <div class='booking-details'>
                    <h3>Booking Details</h3>
                    <p><strong>Confirmation Code:</strong></p>
                    <div class='code'>{$confirmationCode}</div>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    <p><strong>Total Amount:</strong> {$currency} " . number_format($totalAmount, 2) . "</p>
                </div>
                
                <p>Please save this confirmation code for your records.</p>
                <p>We look forward to welcoming you!</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
                <p>If you have any questions, please contact us.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Generating a simple HTML booking request email
 */
function generateSimpleBookingRequestEmail($booking) {
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $email = $booking['email'] ?? '';
    $phone = $booking['phone'] ?? '';
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $guestsCount = $booking['guests_count'] ?? 1;
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e53e3e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 10px 20px; background-color: #4a5568; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Booking Request</h1>
            </div>
            <div class='content'>
                <p>You have received a new booking request:</p>
                
                <div class='booking-details'>
                    <h3>Guest Information</h3>
                    <p><strong>Name:</strong> {$guestName}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Phone:</strong> {$phone}</p>
                    
                    <h3>Booking Details</h3>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    <p><strong>Guests:</strong> {$guestsCount}</p>
                </div>
                
                <p style='text-align: center; margin-top: 20px;'>
                    <a href='" . ADMIN_BOOKINGS_URL . "' class='button'>View Booking in Admin Panel</a>
                </p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel - Admin Panel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Generating a simple HTML host confirmation email
 */
function generateSimpleBookingConfirmedEmail($booking) {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    $confirmationCode = $booking['confirmation_code'] ?? '';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #38a169; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Confirmed!</h1>
            </div>
            <div class='content'>
                <h2>Great News!</h2>
                <p>Dear {$guestName},</p>
                <p>Your booking has been confirmed by our team.</p>
                
                <div class='booking-details'>
                    <h3>Booking Details</h3>
                    <p><strong>Confirmation Code:</strong> {$confirmationCode}</p>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                </div>
                
                <p>We look forward to welcoming you!</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Generating a simple HTML cancellation letter
 */
function generateSimpleBookingCancelledEmail($booking, $reason = '') {
    $checkin = $booking['checkin_date'] ?? '';
    $checkout = $booking['checkout_date'] ?? '';
    $roomName = $booking['room_name'] ?? '';
    $guestName = $booking['guest_name'] ?? '';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e53e3e; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f7fafc; }
            .booking-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Booking Cancellation</h1>
            </div>
            <div class='content'>
                <p>Dear {$guestName},</p>
                <p>We're sorry to inform you that your booking has been cancelled.</p>
                
                <div class='booking-details'>
                    <h3>Cancelled Booking</h3>
                    <p><strong>Room:</strong> {$roomName}</p>
                    <p><strong>Check-in:</strong> {$checkin}</p>
                    <p><strong>Check-out:</strong> {$checkout}</p>
                    " . (!empty($reason) ? "<p><strong>Reason:</strong> {$reason}</p>" : "") . "
                </div>
                
                <p>If you made a payment, a refund will be processed within 5-10 business days.</p>
                <p>We hope to welcome you in the future.</p>
            </div>
            <div class='footer'>
                <p>Back to Base Hotel</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Guest confirmation: wellness request (massage/sauna) received
 *
 * @param array $mb String massage_bookings or compatible array
 */
function sendMassageBookingConfirmationToGuest(array $mb) {
    try {
        $email = $mb['email'] ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Guest email is required');
        }
        $code = htmlspecialchars($mb['confirmation_code'] ?? '', ENT_QUOTES, 'UTF-8');
        $htmlContent = loadEmailTemplate('massage_booking_guest', [
            'booking' => $mb,
            'confirmation_code' => $mb['confirmation_code'] ?? '',
            'site_url' => SITE_URL
        ]);
        if (!$htmlContent) {
            $htmlContent = generateSimpleMassageBookingGuestEmail($mb);
        }
        $subject = 'We received your wellness booking request — Back to Base';
        $ov = btbEmailTemplateApplyAdminOverride('massage_booking_guest', [
            'booking' => $mb,
            'confirmation_code' => $mb['confirmation_code'] ?? '',
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        return sendEmail($email, $ov['subject'], $ov['html']);
    } catch (Exception $e) {
        error_log('sendMassageBookingConfirmationToGuest: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Notification to the administrator about a new massage/sauna request
 *
 * @param array $mb String massage_bookings or compatible array
 */
function sendMassageBookingRequestToHost(array $mb) {
    try {
        $hostEmail = MAILGUN_HOST_EMAIL ?? '';
        if (empty($hostEmail)) {
            logActivity('Host email not configured, skipping massage booking host notification', 'INFO');
            return [
                'success' => true,
                'message' => 'Host email not configured, notification skipped'
            ];
        }
        $htmlContent = loadEmailTemplate('massage_booking_host', [
            'booking' => $mb,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL
        ]);
        if (!$htmlContent) {
            $htmlContent = generateSimpleMassageBookingHostEmail($mb);
        }
        $subject = 'New wellness booking (massage / sauna) — Back to Base';
        $ov = btbEmailTemplateApplyAdminOverride('massage_booking_host', [
            'booking' => $mb,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        return sendEmail($hostEmail, $ov['subject'], $ov['html']);
    } catch (Exception $e) {
        error_log('sendMassageBookingRequestToHost: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * A simple letter to the guest about receiving a request for wellness
 */
function generateSimpleMassageBookingGuestEmail(array $mb) {
    $name = htmlspecialchars($mb['guest_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($mb['confirmation_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($mb['massage_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $dur = (int) ($mb['duration'] ?? 0);
    $date = htmlspecialchars($mb['massage_date'] ?? '', ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($mb['massage_time'] ?? '', ENT_QUOTES, 'UTF-8');
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'>
    <style>
      body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
      .container { max-width: 600px; margin: 0 auto; padding: 20px; }
      .header { background-color: #2d6a4f; color: white; padding: 20px; text-align: center; }
      .content { padding: 20px; background-color: #f7fafc; }
      .details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
      .code { font-size: 20px; font-weight: bold; color: #2d6a4f; }
    </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'><h1>Back to Base</h1></div>
        <div class='content'>
          <p>Dear {$name},</p>
          <p>Thank you — we have received your wellness booking request. We will confirm by email as soon as possible.</p>
          <div class='details'>
            <p><strong>Reference:</strong> <span class='code'>{$code}</span></p>
            <p><strong>Service:</strong> {$type} ({$dur} min)</p>
            <p><strong>Preferred date:</strong> {$date}</p>
            <p><strong>Preferred time:</strong> {$time}</p>
          </div>
          <p>You can review your requests on the site under your guest order summary.</p>
        </div>
      </div>
    </body>
    </html>";
}

/**
 * A simple letter to the administrator about a new wellness request
 */
function generateSimpleMassageBookingHostEmail(array $mb) {
    $name = htmlspecialchars($mb['guest_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($mb['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars($mb['phone'] ?? '', ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($mb['massage_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $dur = (int) ($mb['duration'] ?? 0);
    $date = htmlspecialchars($mb['massage_date'] ?? '', ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($mb['massage_time'] ?? '', ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($mb['confirmation_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $id = (int) ($mb['id'] ?? 0);
    $admin = htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8');
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'>
    <style>
      body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
      .container { max-width: 600px; margin: 0 auto; padding: 20px; }
      .header { background-color: #1e3a5f; color: white; padding: 16px; text-align: center; }
      .content { padding: 20px; background-color: #f7fafc; }
      .details { background: #fff; padding: 15px; border-radius: 5px; }
    </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'><h1>New wellness booking request</h1></div>
        <div class='content'>
          <div class='details'>
            <p><strong>Booking ID:</strong> {$id}</p>
            <p><strong>Confirmation code:</strong> {$code}</p>
            <p><strong>Guest:</strong> {$name}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Phone:</strong> {$phone}</p>
            <p><strong>Service:</strong> {$type} — {$dur} min</p>
            <p><strong>Date:</strong> {$date}</p>
            <p><strong>Time:</strong> {$time}</p>
          </div>
          <p><a href='{$admin}'>Open admin bookings</a></p>
        </div>
      </div>
    </body>
    </html>";
}

/**
 * HTML: old and new room reservation details (for change letters).
 */
function buildRoomBookingBeforeAfterHtml(array $before, array $after) {
    $fmt = function ($key, $row) {
        $v = $row[$key] ?? '';
        if ($key === 'pets') {
            $pn = (int) $v;
            if ($pn < 0) {
                $pn = 0;
            }
            if ($pn > 2) {
                $pn = 2;
            }
            $dogLabels = ['No dogs', '1 dog', '2 dogs'];
            $v = $dogLabels[$pn];
        }
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };
    $lines = function ($label, $row) use ($fmt) {
        $id = (int) ($row['id'] ?? 0);
        $code = htmlspecialchars((string) ($row['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $out = "<p><strong>{$label}</strong> (ID {$id}, ref. {$code})</p><ul style='margin:0 0 12px 18px;'>";
        $out .= '<li><strong>Room:</strong> ' . $fmt('room_name', $row) . '</li>';
        $out .= '<li><strong>Check-in:</strong> ' . $fmt('checkin_date', $row) . '</li>';
        $out .= '<li><strong>Check-out:</strong> ' . $fmt('checkout_date', $row) . '</li>';
        $out .= '<li><strong>Guest:</strong> ' . $fmt('guest_name', $row) . '</li>';
        $out .= '<li><strong>Email:</strong> ' . $fmt('email', $row) . '</li>';
        $out .= '<li><strong>Phone:</strong> ' . $fmt('phone', $row) . '</li>';
        $out .= '<li><strong>Guests:</strong> ' . $fmt('guests_count', $row) . '</li>';
        $out .= '<li><strong>Dogs:</strong> ' . $fmt('pets', $row) . '</li>';
        $out .= '<li><strong>Total (nights):</strong> ' . $fmt('total_amount', $row) . ' ' . htmlspecialchars((string) ($row['currency'] ?? 'CAD'), ENT_QUOTES, 'UTF-8') . '</li>';
        $out .= '</ul>';
        return $out;
    };
    $prev = $lines('Previous details', $before);
    $next = $lines('Updated details', $after);
    return "
    <!DOCTYPE html>
    <html><head><meta charset='UTF-8'>
    <style>
      body { font-family: Arial, sans-serif; line-height: 1.55; color: #333; }
      .box { max-width: 600px; margin: 0 auto; padding: 16px; background: #f7fafc; }
      .head { background: #2d6a4f; color: #fff; padding: 14px 16px; font-size: 18px; }
    </style>
    </head><body>
      <div class='head'>Booking was updated</div>
      <div class='box'>
        <p>The guest edited their booking from the website (My Bookings).</p>
        {$prev}{$next}
        <p style='font-size:13px;color:#555;'>If anything looks wrong, open the booking in the admin panel.</p>
      </div>
    </body></html>";
}

/**
 * Letters to the guest and host after changing the room reservation from the website.
 */
function sendRoomBookingUpdatedToGuestAndHost(array $before, array $after) {
    $html = buildRoomBookingBeforeAfterHtml($before, $after);
    $guest = $after['email'] ?? '';
    if (!filter_var($guest, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Guest email is required');
    }
    $subject = 'Your booking was updated — Back to Base';
    $guestOv = btbEmailTemplateApplyAdminOverride('room_booking_updated_guest', [
        'before' => $before,
        'after' => $after,
        'booking' => $after,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode((string) ($after['confirmation_code'] ?? '')),
        'site_url' => SITE_URL,
    ], $subject, $html);
    sendEmail($guest, $guestOv['subject'], $guestOv['html']);
    $host = MAILGUN_HOST_EMAIL ?? '';
    if (!empty($host) && filter_var($host, FILTER_VALIDATE_EMAIL)) {
        $hostOv = btbEmailTemplateApplyAdminOverride('room_booking_updated_host', [
            'before' => $before,
            'after' => $after,
            'booking' => $after,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode((string) ($after['confirmation_code'] ?? '')),
            'site_url' => SITE_URL,
        ], 'Guest updated a room booking — Back to Base', $html);
        sendEmail($host, $hostOv['subject'], $hostOv['html']);
    }
}

/**
 * HTML: wellness booking before/after change.
 */
function buildMassageBookingBeforeAfterHtml(array $before, array $after) {
    $cell = function ($row) {
        $id = (int) ($row['id'] ?? 0);
        $code = htmlspecialchars((string) ($row['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $g = htmlspecialchars((string) ($row['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $e = htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $p = htmlspecialchars((string) ($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars((string) ($row['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $d = (int) ($row['duration'] ?? 0);
        $dt = htmlspecialchars((string) ($row['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tm = htmlspecialchars((string) ($row['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
        return "<ul style='margin:0 0 12px 18px;'><li><strong>ID / ref:</strong> {$id} / {$code}</li>"
            . "<li><strong>Guest:</strong> {$g}</li><li><strong>Email:</strong> {$e}</li><li><strong>Phone:</strong> {$p}</li>"
            . "<li><strong>Service:</strong> {$t} ({$d} min)</li><li><strong>Date:</strong> {$dt}</li><li><strong>Time:</strong> {$tm}</li></ul>";
    };
    $prev = '<p><strong>Previous</strong></p>' . $cell($before);
    $next = '<p><strong>Updated</strong></p>' . $cell($after);
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#2d6a4f;'>Wellness booking updated</h2><p>The guest saved changes from My Bookings.</p>{$prev}{$next}</body></html>";
}

/**
 * Letters to the guest and host after changing the wellness reservation.
 */
function sendMassageBookingUpdatedToGuestAndHost(array $before, array $after) {
    $html = buildMassageBookingBeforeAfterHtml($before, $after);
    $guest = $after['email'] ?? '';
    if (!filter_var($guest, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Guest email is required');
    }
    $guestOv = btbEmailTemplateApplyAdminOverride('massage_booking_updated_guest', [
        'before' => $before,
        'after' => $after,
        'booking' => $after,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
    ], 'Your wellness booking was updated — Back to Base', $html);
    sendEmail($guest, $guestOv['subject'], $guestOv['html']);
    $host = MAILGUN_HOST_EMAIL ?? '';
    if (!empty($host) && filter_var($host, FILTER_VALIDATE_EMAIL)) {
        $hostOv = btbEmailTemplateApplyAdminOverride('massage_booking_updated_host', [
            'before' => $before,
            'after' => $after,
            'booking' => $after,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], 'Guest updated a wellness booking — Back to Base', $html);
        sendEmail($host, $hostOv['subject'], $hostOv['html']);
    }
}

/**
 * Notify guest that wellness booking is confirmed by host/admin.
 */
function sendMassageBookingConfirmedToGuest(array $mb) {
    $email = trim((string) ($mb['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Guest email is required'];
    }
    $name = htmlspecialchars((string) ($mb['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars((string) ($mb['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars((string) ($mb['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($mb['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string) ($mb['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dur = (int) ($mb['duration'] ?? 0);
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#2d6a4f;'>Wellness booking confirmed</h2>"
        . "<p>Dear {$name}, your wellness booking is confirmed.</p>"
        . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Service:</strong> {$type} ({$dur} min)</li>"
        . "<li><strong>Date:</strong> {$date}</li><li><strong>Time:</strong> {$time}</li></ul>"
        . "<p>We look forward to welcoming you at Back to Base.</p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('massage_booking_confirmed_guest', [
        'booking' => $mb,
        'confirmation_code' => $mb['confirmation_code'] ?? '',
        'site_url' => SITE_URL,
    ], 'Your wellness booking is confirmed — Back to Base', $html);
    return sendEmail($email, $ov['subject'], $ov['html'], '', ['template_key' => 'massage_booking_confirmed_guest', 'context' => ['booking' => $mb]]);
}

/**
 * Notify guest that wellness booking is cancelled by host/admin.
 */
function sendMassageBookingCancelledToGuest(array $mb, string $reason = '') {
    $email = trim((string) ($mb['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Guest email is required'];
    }
    $name = htmlspecialchars((string) ($mb['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars((string) ($mb['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars((string) ($mb['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($mb['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string) ($mb['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reasonHtml = $reason !== '' ? '<p><strong>Reason:</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#8b1e3f;'>Wellness booking cancelled</h2>"
        . "<p>Dear {$name}, we are sorry to inform you your wellness booking was cancelled.</p>"
        . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Service:</strong> {$type}</li>"
        . "<li><strong>Date:</strong> {$date}</li><li><strong>Time:</strong> {$time}</li></ul>"
        . $reasonHtml
        . "<p>Please contact us if you'd like to reschedule.</p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('massage_booking_cancelled_guest', [
        'booking' => $mb,
        'reason' => $reason,
        'site_url' => SITE_URL,
    ], 'Your wellness booking was cancelled — Back to Base', $html);
    return sendEmail($email, $ov['subject'], $ov['html'], '', ['template_key' => 'massage_booking_cancelled_guest', 'context' => ['booking' => $mb, 'reason' => $reason]]);
}

/**
 * Welcome email right after successful account registration.
 */
function sendUserRegistrationWelcomeEmail(array $user) {
    $email = trim((string) ($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'User email is required'];
    }
    $name = htmlspecialchars((string) ($user['name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#1e3a5f;'>Welcome to Back to Base</h2>"
        . "<p>Hello {$name}, your account was created successfully.</p>"
        . "<p>You can manage your bookings and messages in your account dashboard.</p>"
        . "<p><a href='" . htmlspecialchars(SITE_URL . "/login.html", ENT_QUOTES, 'UTF-8') . "'>Open account</a></p>"
        . "</body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('user_register_welcome', [
        'user' => $user,
        'site_url' => SITE_URL,
    ], 'Welcome to Back to Base', $html);
    return sendEmail($email, $ov['subject'], $ov['html'], '', ['template_key' => 'user_register_welcome', 'context' => ['user' => $user]]);
}

/**
 * Login notification email to account owner.
 */
function sendUserLoginNotificationEmail(array $user, array $meta = []) {
    $email = trim((string) ($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'User email is required'];
    }
    $name = htmlspecialchars((string) ($user['name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
    $ip = htmlspecialchars((string) ($meta['ip'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ua = htmlspecialchars((string) ($meta['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string) ($meta['time'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8');
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#1e3a5f;'>Login to your account</h2>"
        . "<p>Hello {$name}, we noticed a sign-in to your Back to Base account.</p>"
        . "<ul><li><strong>Time:</strong> {$time}</li><li><strong>IP:</strong> {$ip}</li><li><strong>Device:</strong> {$ua}</li></ul>"
        . "<p>If this wasn't you, please change your password immediately.</p>"
        . "</body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('user_login_notification', [
        'user' => $user,
        'login_meta' => $meta,
        'site_url' => SITE_URL,
    ], 'Login detected in your Back to Base account', $html);
    return sendEmail($email, $ov['subject'], $ov['html'], '', ['template_key' => 'user_login_notification', 'context' => ['user' => $user, 'login_meta' => $meta]]);
}

/**
 * Payment success notification to guest and host.
 */
function sendRoomPaymentSucceededToGuestAndHost(array $booking, string $paymentIntentId = '') {
    $guestEmail = trim((string) ($booking['email'] ?? ''));
    if (filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        $guestName = htmlspecialchars((string) ($booking['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
        $room = htmlspecialchars((string) ($booking['room_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $checkin = htmlspecialchars((string) ($booking['checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $checkout = htmlspecialchars((string) ($booking['checkout_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string) ($booking['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars((string) ($booking['currency'] ?? 'CAD'), ENT_QUOTES, 'UTF-8');
        $pi = htmlspecialchars($paymentIntentId, ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
            . "<h2 style='color:#2d6a4f;'>Payment received</h2><p>Dear {$guestName}, your payment was received successfully.</p>"
            . "<ul><li><strong>Room:</strong> {$room}</li><li><strong>Check-in:</strong> {$checkin}</li><li><strong>Check-out:</strong> {$checkout}</li>"
            . "<li><strong>Amount:</strong> {$amount} {$currency}</li><li><strong>Payment reference:</strong> {$pi}</li></ul></body></html>";
        $guestOv = btbEmailTemplateApplyAdminOverride('booking_payment_succeeded_guest', [
            'booking' => $booking,
            'payment_intent_id' => $paymentIntentId,
            'site_url' => SITE_URL,
        ], 'Payment received for your booking — Back to Base', $html);
        sendEmail($guestEmail, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'booking_payment_succeeded_guest', 'context' => ['booking' => $booking, 'payment_intent_id' => $paymentIntentId]]);
    }
    $hostEmail = trim((string) (MAILGUN_HOST_EMAIL ?? ''));
    if (filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        $bookingId = (int) ($booking['id'] ?? 0);
        $guest = htmlspecialchars((string) ($booking['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string) ($booking['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars((string) ($booking['currency'] ?? 'CAD'), ENT_QUOTES, 'UTF-8');
        $pi = htmlspecialchars($paymentIntentId, ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
            . "<h2 style='color:#1e3a5f;'>Booking payment succeeded</h2>"
            . "<p>Booking #{$bookingId} for {$guest} has been paid.</p>"
            . "<ul><li><strong>Amount:</strong> {$amount} {$currency}</li><li><strong>Payment reference:</strong> {$pi}</li></ul>"
            . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
        $hostOv = btbEmailTemplateApplyAdminOverride('booking_payment_succeeded_host', [
            'booking' => $booking,
            'payment_intent_id' => $paymentIntentId,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], 'Booking payment received — Back to Base', $html);
        sendEmail($hostEmail, $hostOv['subject'], $hostOv['html'], '', ['template_key' => 'booking_payment_succeeded_host', 'context' => ['booking' => $booking, 'payment_intent_id' => $paymentIntentId]]);
    }
}

?>

