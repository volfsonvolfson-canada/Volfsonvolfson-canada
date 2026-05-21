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
function btb_send_email_via_mailgun($to, $subject, $htmlContent, $textContent = '', array $sendOptions = []): array {
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
        $tk = isset($sendOptions['template_key']) ? trim((string) $sendOptions['template_key']) : '';
        $htmlContent = btb_email_finalize_outbound_html($htmlContent, $conn ?? null, $tk !== '' ? $tk : null);
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

/** Whether outbound mail to the staff/host inbox (MAILGUN_HOST_EMAIL) is allowed. */
function btb_email_host_notifications_enabled(): bool
{
    return !defined('BTB_EMAIL_HOST_NOTIFICATIONS_ENABLED') || (bool) BTB_EMAIL_HOST_NOTIFICATIONS_ENABLED;
}

function btb_email_recipient_is_host_inbox(string $to): bool
{
    $toNorm = strtolower(trim($to));
    if ($toNorm === '') {
        return false;
    }
    if (function_exists('btb_host_notification_emails')) {
        foreach (btb_host_notification_emails() as $host) {
            if (strcasecmp($toNorm, $host) === 0) {
                return true;
            }
        }

        return false;
    }
    $host = trim((string) (defined('MAILGUN_HOST_EMAIL') ? MAILGUN_HOST_EMAIL : ''));

    return $host !== '' && strcasecmp($toNorm, strtolower($host)) === 0;
}

/**
 * Send the same message to every configured host inbox.
 *
 * @param array<string,mixed> $options Passed through to sendEmail()
 *
 * @return array<string,mixed> Last send result; success true if at least one inbox accepted the mail
 */
function btb_send_email_to_host_inboxes(string $subject, string $htmlContent, string $textContent = '', array $options = []): array
{
    $inboxes = function_exists('btb_host_notification_emails') ? btb_host_notification_emails() : [];
    if ($inboxes === [] && function_exists('btb_host_notification_email')) {
        $one = btb_host_notification_email();
        if ($one !== '') {
            $inboxes = [$one];
        }
    }
    if ($inboxes === []) {
        return ['success' => false, 'error' => 'No host inbox configured'];
    }

    $last = ['success' => false, 'error' => 'No send attempted'];
    $anySent = false;
    foreach ($inboxes as $to) {
        $last = sendEmail($to, $subject, $htmlContent, $textContent, $options);
        if (!empty($last['success']) && empty($last['skipped'])) {
            $anySent = true;
        }
    }
    if ($anySent) {
        $last['success'] = true;
    }

    return $last;
}

/**
 * Sending email via queue (default) or direct Mailgun.
 *
 * @param array<string,mixed> $options ['force_send'=>bool,'template_key'=>string,'context'=>array]
 */
function sendEmail($to, $subject, $htmlContent, $textContent = '', array $options = []) {
    try {
        if (!btb_email_host_notifications_enabled() && btb_email_recipient_is_host_inbox((string) $to)) {
            $tk = isset($options['template_key']) ? (string) $options['template_key'] : '';
            $detail = $tk !== '' ? "template_key={$tk}, " : '';
            logActivity("Host inbox email skipped (BTB_EMAIL_HOST_NOTIFICATIONS_ENABLED=false): {$detail}to {$to}", 'INFO');

            return ['success' => true, 'skipped' => true, 'reason' => 'host_notifications_disabled'];
        }
        $forceSend = !empty($options['force_send']);
        if (!$forceSend && btb_email_queue_enabled()) {
            $q = btb_email_enqueue($to, $subject, $htmlContent, $textContent, $options);
            if ($q['success']) {
                return $q;
            }
            // Fail-open fallback: try direct send so business flow does not drop email.
            error_log('Email enqueue failed; fallback to direct send: ' . ($q['error'] ?? 'unknown'));
        }
        $res = btb_send_email_via_mailgun($to, $subject, $htmlContent, $textContent, $options);
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
        $result = sendEmail($email, $ov['subject'], $ov['html'], '', [
            'template_key' => 'booking_confirmation_guest',
            'context' => ['booking' => $booking],
        ]);
        
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
function btb_host_booking_request_emails_enabled(): bool
{
    if (!btb_email_host_notifications_enabled()) {
        return false;
    }
    if (!defined('BTB_HOST_BOOKING_REQUEST_EMAILS_ENABLED')) {
        return true;
    }

    return (bool) BTB_HOST_BOOKING_REQUEST_EMAILS_ENABLED;
}

function sendBookingRequestToHost($booking) {
    try {
        if (!btb_host_booking_request_emails_enabled()) {
            $skipReason = !btb_email_host_notifications_enabled()
                ? 'BTB_EMAIL_HOST_NOTIFICATIONS_ENABLED is off'
                : 'BTB_HOST_BOOKING_REQUEST_EMAILS_ENABLED is off';
            logActivity("Host booking request email skipped ({$skipReason})", 'INFO');

            return [
                'success' => true,
                'message' => 'Host booking request emails temporarily disabled',
            ];
        }

        $hostEmail = btb_host_notification_email();
        
        if (empty($hostEmail)) {
            // If the host email is not configured, just log it
            logActivity("Host email not configured, skipping booking request notification", 'INFO');
            return [
                'success' => true,
                'message' => 'Host email not configured, notification skipped'
            ];
        }
        
        // Uploading a letter template
        $guestMsg = function_exists('btb_normalize_guest_message')
            ? btb_normalize_guest_message($booking['special_requests'] ?? '')
            : trim((string) ($booking['special_requests'] ?? ''));

        $htmlContent = loadEmailTemplate('booking_request', [
            'booking' => $booking,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ]);
        
        if (!$htmlContent) {
            // If the template is not found, use a simple format
            $htmlContent = generateSimpleBookingRequestEmail($booking);
        }
        
        $subject = 'New Booking Request - Back to Base Hotel';
        $ov = btbEmailTemplateApplyAdminOverride('booking_request_host', [
            'booking' => $booking,
            'booking_guest_message' => $guestMsg,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        
        // Sending a letter
        return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'booking_request_host']);

    } catch (Exception $e) {
        error_log("Send booking request to host error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/** Normalized guest email for matching bookings rows. */
function btb_guest_email_normalize(string $email): string
{
    return strtolower(trim($email));
}

/** Hours of history to include in the guest digest (resolved rows). */
function btb_guest_digest_lookback_hours(): int
{
    return 168;
}

/**
 * True if this guest still has any room or wellness request awaiting staff action.
 */
function btb_guest_has_pending_bookings($conn, string $emailNorm): bool
{
    if (!$conn || $emailNorm === '') {
        return false;
    }
    $row = fetchOne(
        $conn,
        'SELECT (
            (SELECT COUNT(*) FROM bookings WHERE LOWER(TRIM(email)) = ? AND LOWER(TRIM(status)) = \'pending\')
          + (SELECT COUNT(*) FROM massage_bookings WHERE LOWER(TRIM(email)) = ? AND LOWER(TRIM(status)) = \'pending\')
        ) AS c',
        [$emailNorm, $emailNorm]
    );

    return $row !== false && intval($row['c'] ?? 0) > 0;
}

/** @return array<string,mixed>|null */
function btb_guest_digest_fetch_room_row($conn, int $id): ?array
{
    if ($id <= 0 || !$conn) {
        return null;
    }
    if (function_exists('getBookingById')) {
        $r = getBookingById($conn, $id);

        return is_array($r) ? $r : null;
    }
    $sql = 'SELECT b.*, bc.confirmation_code FROM bookings b
            LEFT JOIN booking_confirmations bc ON bc.booking_id = b.id WHERE b.id = ? LIMIT 1';
    $r = fetchOne($conn, $sql, [$id]);

    return is_array($r) ? $r : null;
}

/** @return array<string,mixed>|null */
function btb_guest_digest_fetch_massage_row($conn, int $id): ?array
{
    if ($id <= 0 || !$conn) {
        return null;
    }
    if (function_exists('getMassageBookingById')) {
        $r = getMassageBookingById($conn, $id);

        return is_array($r) ? $r : null;
    }
    $r = fetchOne($conn, 'SELECT * FROM massage_bookings WHERE id = ? LIMIT 1', [$id]);

    return is_array($r) ? $r : null;
}

/**
 * Payable totals for confirmed-but-unpaid lines (matches combined Stripe prepare: taxes on booking subtotal only).
 *
 * @param array<int,array<string,mixed>> $rooms
 * @param array<int,array<string,mixed>> $massages
 *
 * @return array{taxable:float,tax1:float,tax2:float,tax3:float,grand:float,currency:string,tax1_label:string,tax2_label:string,tax3_label:string,needs_pay_online:bool}
 */
function btb_guest_digest_compute_pay_totals($conn, array $rooms, array $massages): array
{
    $taxable = 0.0;
    $roomTaxable = 0.0;
    $currency = '';
    foreach ($rooms as $b) {
        if (strtolower(trim((string) ($b['status'] ?? ''))) !== 'confirmed') {
            continue;
        }
        if (strtolower(trim((string) ($b['payment_status'] ?? ''))) === 'paid') {
            continue;
        }
        $amt = floatval($b['total_amount'] ?? 0);
        if ($amt <= 0) {
            continue;
        }
        $taxable += $amt;
        $roomTaxable += $amt;
        if ($currency === '') {
            $currency = strtoupper(trim((string) ($b['currency'] ?? '')));
        }
    }
    foreach ($massages as $mb) {
        if (strtolower(trim((string) ($mb['status'] ?? ''))) !== 'confirmed') {
            continue;
        }
        if (isset($mb['payment_status']) && strtolower(trim((string) $mb['payment_status'])) === 'paid') {
            continue;
        }
        $amt = floatval($mb['total_amount'] ?? 0);
        if ($amt <= 0) {
            continue;
        }
        $taxable += $amt;
        if ($currency === '') {
            $currency = strtoupper(trim((string) ($mb['currency'] ?? '')));
        }
    }
    $taxable = round($taxable, 2);
    $roomTaxable = round($roomTaxable, 2);
    $pricing = function_exists('btb_my_bookings_pricing_api_data') ? btb_my_bookings_pricing_api_data($conn) : [];
    $taxes = function_exists('btb_taxes_on_taxable_subtotal')
        ? btb_taxes_on_taxable_subtotal($conn, $roomTaxable)
        : ['tax1' => 0.0, 'tax2' => 0.0, 'tax3' => 0.0, 'grand' => $roomTaxable];
    $tax1 = (float) ($taxes['tax1'] ?? 0);
    $tax2 = (float) ($taxes['tax2'] ?? 0);
    $tax3 = (float) ($taxes['tax3'] ?? 0);
    $grand = round($taxable + $tax1 + $tax2 + $tax3, 2);
    if ($currency === '' && defined('STRIPE_CURRENCY')) {
        $currency = strtoupper((string) STRIPE_CURRENCY);
    }
    if ($currency === '') {
        $currency = 'CAD';
    }
    $needsPayOnline = $grand > 0 && !empty(STRIPE_SECRET_KEY);

    return [
        'taxable' => $taxable,
        'tax1' => $tax1,
        'tax2' => $tax2,
        'tax3' => $tax3,
        'grand' => $grand,
        'currency' => $currency,
        'tax1_label' => (string) ($pricing['tax1_label'] ?? 'GST'),
        'tax2_label' => (string) ($pricing['tax2_label'] ?? 'PST'),
        'tax3_label' => (string) ($pricing['tax3_label'] ?? 'Tax 3'),
        'needs_pay_online' => $needsPayOnline,
    ];
}

function btb_guest_digest_format_money(float $amount, string $currency): string
{
    return htmlspecialchars($currency . ' ' . number_format($amount, 2), ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<int,array<string,mixed>> $roomsById
 * @param array<int,array<string,mixed>> $massagesById
 * @param array<int,array<string,mixed>> $deletedRooms
 * @param array<int,array<string,mixed>> $deletedMassages
 * @param array<string,mixed> $payTotals from btb_guest_digest_compute_pay_totals
 */
function btb_guest_digest_section_heading(string $label): string
{
    $t = htmlspecialchars(trim($label), ENT_QUOTES, 'UTF-8');
    if ($t === '') {
        return '';
    }
    $font = 'font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;';

    return '<p class="btb-email-digest-section" style="margin:26px 0 12px;' . $font . 'font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#5c6b7a;">' . $t . '</p>';
}

/** @return array{label:string,bg:string,color:string}|null */
function btb_guest_digest_item_badge_meta(string $kind): ?array
{
    static $map = [
        'approved' => ['label' => 'Approved', 'bg' => '#d1fae5', 'color' => '#065f46'],
        'declined' => ['label' => 'Not approved', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        'removed' => ['label' => 'Removed', 'bg' => '#f3f4f6', 'color' => '#374151'],
    ];

    return $map[$kind] ?? null;
}

/**
 * @return array<int, array{0:string,1:string}>
 */
function btb_guest_digest_room_card_pairs(array $b, bool $isRemoved = false): array
{
    $rows = [];
    $rn = btb_email_tpl_cell($b, 'room_name');
    if ($rn !== '') {
        $rows[] = ['Room', $rn];
    }
    btb_email_template_append_room_stay_date_rows($rows, $b);
    $guests = btb_email_template_guests_and_pets_line($b);
    if ($guests !== '') {
        $rows[] = ['Guests', $guests];
    }
    $code = btb_email_tpl_cell($b, 'confirmation_code');
    if ($code !== '') {
        $rows[] = ['Confirmation code', $code];
    }
    if ($isRemoved) {
        $prevSt = trim((string) ($b['status_before'] ?? ''));
        if ($prevSt === '') {
            $prevSt = trim((string) ($b['status'] ?? ''));
        }
        if ($prevSt !== '') {
            $rows[] = ['Previous status', $prevSt];
        }
    } else {
        $st = strtolower(trim((string) ($b['status'] ?? '')));
        $amt = floatval($b['total_amount'] ?? 0);
        $cur = strtoupper(trim((string) ($b['currency'] ?? 'CAD')));
        $pay = strtolower(trim((string) ($b['payment_status'] ?? '')));
        if ($amt > 0) {
            $money = $cur . ' ' . number_format($amt, 2);
            $payNote = '';
            if ($st === 'confirmed') {
                $payNote = $pay === 'paid' ? 'Paid' : 'Payment pending';
            }
            $rows[] = ['Amount', $payNote !== '' ? ($money . ' — ' . $payNote) : $money];
        }
    }

    return $rows;
}

/**
 * @return array<int, array{0:string,1:string}>
 */
function btb_guest_digest_massage_card_pairs(array $mb, bool $isRemoved = false): array
{
    $rows = [];
    $svc = btb_email_template_wellness_service_line($mb);
    if ($svc !== '') {
        $rows[] = ['Service', $svc];
    }
    btb_email_template_append_wellness_date_time_rows($rows, $mb);
    $code = btb_email_tpl_cell($mb, 'confirmation_code');
    if ($code !== '') {
        $rows[] = ['Confirmation code', $code];
    }
    if ($isRemoved) {
        $prevSt = trim((string) ($mb['status_before'] ?? ''));
        if ($prevSt === '') {
            $prevSt = trim((string) ($mb['status'] ?? ''));
        }
        if ($prevSt !== '') {
            $rows[] = ['Previous status', $prevSt];
        }
    } else {
        $st = strtolower(trim((string) ($mb['status'] ?? '')));
        $amt = floatval($mb['total_amount'] ?? 0);
        $cur = strtoupper(trim((string) ($mb['currency'] ?? 'CAD')));
        $pay = strtolower(trim((string) ($mb['payment_status'] ?? '')));
        if ($amt > 0) {
            $money = $cur . ' ' . number_format($amt, 2);
            $payNote = '';
            if ($st === 'confirmed') {
                $payNote = $pay === 'paid' ? 'Paid' : 'Payment pending';
            }
            $rows[] = ['Amount', $payNote !== '' ? ($money . ' — ' . $payNote) : $money];
        }
    }

    return $rows;
}

function btb_guest_digest_render_booking_card(string $cardTitle, array $pairs, ?array $badgeMeta): string
{
    return btb_email_template_build_details_card_html(
        $cardTitle,
        $pairs,
        $badgeMeta,
        'margin:0 0 16px'
    );
}

function btb_guest_digest_build_inner_html(
    array $roomsById,
    array $massagesById,
    array $deletedRooms,
    array $deletedMassages,
    array $payTotals,
    string $ordersUrl,
    string $cancelReasonNote = ''
): string {
    $esc = static function ($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };

    $approvedRooms = [];
    $declinedRooms = [];
    foreach ($roomsById as $b) {
        $st = strtolower(trim((string) ($b['status'] ?? '')));
        if ($st === 'confirmed') {
            $approvedRooms[] = $b;
        } elseif ($st === 'cancelled') {
            $declinedRooms[] = $b;
        }
    }
    $approvedMb = [];
    $declinedMb = [];
    foreach ($massagesById as $mb) {
        $st = strtolower(trim((string) ($mb['status'] ?? '')));
        if ($st === 'confirmed') {
            $approvedMb[] = $mb;
        } elseif ($st === 'cancelled') {
            $declinedMb[] = $mb;
        }
    }

    $parts = [];
    if ($cancelReasonNote !== '') {
        $parts[] = '<p style="font-size:14px;color:#555;"><strong>Note:</strong> ' . $esc($cancelReasonNote) . '</p>';
    }

    $approvedBadge = btb_guest_digest_item_badge_meta('approved');
    $declinedBadge = btb_guest_digest_item_badge_meta('declined');
    $removedBadge = btb_guest_digest_item_badge_meta('removed');

    if ($approvedRooms !== [] || $approvedMb !== []) {
        $parts[] = btb_guest_digest_section_heading('Approved');
        foreach ($approvedRooms as $b) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Room stay',
                btb_guest_digest_room_card_pairs($b),
                $approvedBadge
            );
        }
        foreach ($approvedMb as $mb) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Wellness',
                btb_guest_digest_massage_card_pairs($mb),
                $approvedBadge
            );
        }
    }

    if ($declinedRooms !== [] || $declinedMb !== []) {
        $parts[] = btb_guest_digest_section_heading('Not approved (cancelled)');
        foreach ($declinedRooms as $b) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Room stay',
                btb_guest_digest_room_card_pairs($b),
                $declinedBadge
            );
        }
        foreach ($declinedMb as $mb) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Wellness',
                btb_guest_digest_massage_card_pairs($mb),
                $declinedBadge
            );
        }
    }

    if ($deletedRooms !== [] || $deletedMassages !== []) {
        $parts[] = btb_guest_digest_section_heading('Removed');
        $parts[] = '<p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#64748b;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">These entries were removed from our booking list.</p>';
        foreach ($deletedRooms as $snap) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Room stay',
                btb_guest_digest_room_card_pairs($snap, true),
                $removedBadge
            );
        }
        foreach ($deletedMassages as $snap) {
            $parts[] = btb_guest_digest_render_booking_card(
                'Wellness',
                btb_guest_digest_massage_card_pairs($snap, true),
                $removedBadge
            );
        }
    }

    $cur = $payTotals['currency'] ?? 'CAD';
    $balanceRows = [];
    if ($payTotals['taxable'] <= 0) {
        $balanceRows[] = ['Balance', 'No unpaid balance is due for the approved items above (or amounts are zero).'];
    } else {
        $balanceRows[] = ['Confirmed stays & wellness (before tax)', btb_guest_digest_format_money(floatval($payTotals['taxable']), $cur)];
        if (floatval($payTotals['tax1']) > 0) {
            $balanceRows[] = [
                (string) ($payTotals['tax1_label'] ?? 'GST'),
                btb_guest_digest_format_money(floatval($payTotals['tax1']), $cur),
            ];
        }
        if (floatval($payTotals['tax2']) > 0) {
            $balanceRows[] = [
                (string) ($payTotals['tax2_label'] ?? 'PST'),
                btb_guest_digest_format_money(floatval($payTotals['tax2']), $cur),
            ];
        }
        if (floatval($payTotals['tax3'] ?? 0) > 0) {
            $balanceRows[] = [
                (string) ($payTotals['tax3_label'] ?? 'Tax 3'),
                btb_guest_digest_format_money(floatval($payTotals['tax3']), $cur),
            ];
        }
        $balanceRows[] = ['Estimated total due online', btb_guest_digest_format_money(floatval($payTotals['grand']), $cur)];
        $balanceRows[] = ['Note', 'Taxes follow the same rules as the combined checkout on the website.'];
    }
    $parts[] = btb_email_template_build_details_card_html('Balances', $balanceRows, null, 'margin:22px 0 16px');

    $ordersEsc = $esc(btbEmailTemplateSafeHref($ordersUrl));
    /* Match site light-theme .btn.primary (--brand #2563eb, --brand-600 #1d4ed8) */
    $ctaStyle = 'display:inline-block;padding:14px 22px;background:#2563eb !important;'
        . 'color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;'
        . 'text-decoration:none !important;border-radius:10px;font-weight:700;'
        . 'border:2px solid #1d4ed8 !important;line-height:1.35;mso-line-height-rule:exactly;';
    $cta = '<p style="text-align:center;margin:22px 0;"><a href="' . $ordersEsc
        . '" class="btb-email-digest-cta" style="' . $ctaStyle . '">Open My Bookings</a></p>';

    if (!empty($payTotals['needs_pay_online'])) {
        $parts[] = '<p>Please open <strong>My Bookings</strong> to complete payment when you are ready.</p>' . $cta;
    } else {
        $parts[] = '<p>You can always review details in My Bookings.</p>' . $cta;
    }

    return implode('', $parts);
}

/** Default H1 for digest emails (admin template may override `heading`). */
function btb_guest_digest_default_heading(): string
{
    return 'Your booking requests — summary';
}

/**
 * Demo HTML body for admin preview of guest_bookings_digest_guest (no DB / mail send).
 */
function btb_guest_digest_preview_sample_inner_html(): string
{
    $roomsById = [
        1 => [
            'id' => 1,
            'guest_name' => 'Alex',
            'email' => 'guest@example.com',
            'room_name' => 'The Nouk',
            'checkin_date' => '2026-06-01',
            'checkout_date' => '2026-06-05',
            'confirmation_code' => '142-412',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'total_amount' => '320.00',
            'currency' => 'CAD',
        ],
        2 => [
            'id' => 2,
            'guest_name' => 'Alex',
            'room_name' => 'Kelder suite',
            'checkin_date' => '2026-07-10',
            'checkout_date' => '2026-07-12',
            'confirmation_code' => '555-010',
            'status' => 'cancelled',
            'payment_status' => '',
            'total_amount' => '0',
            'currency' => 'CAD',
        ],
    ];
    $massagesById = [
        5 => [
            'id' => 5,
            'guest_name' => 'Alex',
            'massage_type' => 'Relaxation massage',
            'duration' => 60,
            'massage_date' => '2026-06-02',
            'massage_time' => '14:00',
            'confirmation_code' => '888-999',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'total_amount' => '130.00',
            'currency' => 'CAD',
        ],
    ];
    $payTotals = [
        'taxable' => 450.0,
        'tax1' => 22.5,
        'tax2' => 0.0,
        'tax3' => 0.0,
        'grand' => 472.5,
        'currency' => 'CAD',
        'tax1_label' => 'GST',
        'tax2_label' => 'PST',
        'tax3_label' => 'Tax 3',
        'needs_pay_online' => true,
    ];
    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : 'https://example.com';

    return btb_guest_digest_build_inner_html(
        $roomsById,
        $massagesById,
        [],
        [],
        $payTotals,
        $site !== '' ? ($site . '/order.html') : '/order.html',
        ''
    );
}

/**
 * Send one guest digest when nothing is left in pending for this email (staff confirmed / cancelled / deleted).
 *
 * @param array{
 *   room_ids?:array<int|mixed>,
 *   massage_ids?:array<int|mixed>,
 *   deleted_rooms?:array<int,array<string,mixed>>,
 *   deleted_massages?:array<int,array<string,mixed>>,
 *   cancel_reason?:string
 * } $opts
 */
function btb_maybe_send_guest_bookings_digest($conn, string $guestEmailRaw, array $opts = []): void
{
    if (empty(MAILGUN_API_KEY) || !$conn) {
        return;
    }
    $to = trim($guestEmailRaw);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $emailNorm = btb_guest_email_normalize($to);
    if (btb_guest_has_pending_bookings($conn, $emailNorm)) {
        return;
    }

    $hours = max(1, min(8760, (int) btb_guest_digest_lookback_hours()));

    $sqlRooms = "SELECT b.*, bc.confirmation_code FROM bookings b
        LEFT JOIN booking_confirmations bc ON bc.booking_id = b.id
        WHERE LOWER(TRIM(b.email)) = ?
          AND LOWER(TRIM(b.status)) IN ('confirmed','cancelled')
          AND b.updated_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)";
    $rooms = fetchAll($conn, $sqlRooms, [$emailNorm]);
    if ($rooms === false) {
        $rooms = [];
    }

    $mbTimeClause = '';
    if (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'updated_at')) {
        $mbTimeClause = " AND updated_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)";
    } elseif (function_exists('btb_massage_bookings_column_exists') && btb_massage_bookings_column_exists($conn, 'created_at')) {
        $mbTimeClause = " AND created_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)";
    }
    $sqlMb = "SELECT * FROM massage_bookings WHERE LOWER(TRIM(email)) = ?
        AND LOWER(TRIM(status)) IN ('confirmed','cancelled')" . $mbTimeClause;
    $massages = $mbTimeClause !== '' ? fetchAll($conn, $sqlMb, [$emailNorm]) : [];
    if ($massages === false) {
        $massages = [];
    }

    $roomsById = [];
    foreach ($rooms as $r) {
        $roomsById[(int) ($r['id'] ?? 0)] = $r;
    }
    $massagesById = [];
    foreach ($massages as $m) {
        $massagesById[(int) ($m['id'] ?? 0)] = $m;
    }

    foreach (($opts['room_ids'] ?? []) as $rid) {
        $rid = (int) $rid;
        if ($rid <= 0 || isset($roomsById[$rid])) {
            continue;
        }
        $row = btb_guest_digest_fetch_room_row($conn, $rid);
        if (!$row || btb_guest_email_normalize((string) ($row['email'] ?? '')) !== $emailNorm) {
            continue;
        }
        $st = strtolower(trim((string) ($row['status'] ?? '')));
        if ($st === 'confirmed' || $st === 'cancelled') {
            $roomsById[$rid] = $row;
        }
    }
    foreach (($opts['massage_ids'] ?? []) as $mid) {
        $mid = (int) $mid;
        if ($mid <= 0 || isset($massagesById[$mid])) {
            continue;
        }
        $row = btb_guest_digest_fetch_massage_row($conn, $mid);
        if (!$row || btb_guest_email_normalize((string) ($row['email'] ?? '')) !== $emailNorm) {
            continue;
        }
        $st = strtolower(trim((string) ($row['status'] ?? '')));
        if ($st === 'confirmed' || $st === 'cancelled') {
            $massagesById[$mid] = $row;
        }
    }

    $deletedRooms = [];
    if (!empty($opts['deleted_rooms']) && is_array($opts['deleted_rooms'])) {
        foreach ($opts['deleted_rooms'] as $snap) {
            if (is_array($snap)) {
                $deletedRooms[] = $snap;
            }
        }
    }
    $deletedMassages = [];
    if (!empty($opts['deleted_massages']) && is_array($opts['deleted_massages'])) {
        foreach ($opts['deleted_massages'] as $snap) {
            if (is_array($snap)) {
                $deletedMassages[] = $snap;
            }
        }
    }

    $roomsById = array_filter($roomsById, static function ($row) {
        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    });
    $massagesById = array_filter($massagesById, static function ($row) {
        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    });

    if ($roomsById === [] && $massagesById === [] && $deletedRooms === [] && $deletedMassages === []) {
        return;
    }

    $payTotals = btb_guest_digest_compute_pay_totals($conn, array_values($roomsById), array_values($massagesById));
    $ordersUrl = function_exists('btb_guest_orders_url') ? btb_guest_orders_url() : '/order.html';
    $reasonNote = trim((string) ($opts['cancel_reason'] ?? ''));

    $digestInner = btb_guest_digest_build_inner_html(
        $roomsById,
        $massagesById,
        $deletedRooms,
        $deletedMassages,
        $payTotals,
        $ordersUrl,
        $reasonNote
    );

    $subject = 'Your booking requests — summary — Back to Base Hotel';
    $tplVars = [
        'site_url' => SITE_URL,
        'guest_orders_url' => $ordersUrl,
        'digest_inner_html' => $digestInner,
        'pay_totals' => $payTotals,
    ];
    $fallbackHtml = '<p style="font-family:Inter,system-ui,Arial,sans-serif;font-size:15px;color:#334155;">'
        . 'Your booking summary could not be loaded. Please open My Bookings on the website.</p>';
    $ov = btbEmailTemplateApplyAdminOverride('guest_bookings_digest_guest', $tplVars, $subject, $fallbackHtml);
    sendEmail($to, $ov['subject'], $ov['html'], '', [
        'template_key' => 'guest_bookings_digest_guest',
        'context' => [
            'guest_orders_url' => $ordersUrl,
            'pay_totals' => $payTotals,
        ],
    ]);
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

        $payUrl = '';
        if (!empty(STRIPE_SECRET_KEY) && floatval($booking['total_amount'] ?? 0) > 0 && function_exists('btb_guest_room_pay_url')) {
            $payUrl = btb_guest_room_pay_url($booking);
        }
        
        // Uploading a letter template
        $htmlContent = loadEmailTemplate('booking_confirmed', [
            'booking' => $booking,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode($booking['confirmation_code'] ?? ''),
            'site_url' => SITE_URL,
            'pay_url' => $payUrl,
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
            'pay_url' => $payUrl,
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

/** @param array<string,mixed>|null $row */
function btb_email_tpl_cell(?array $row, string $key): string
{
    if ($row === null || !is_array($row)) {
        return '';
    }

    return trim((string) ($row[$key] ?? ''));
}

/**
 * Remove legacy "Label: {{booking.xxx}}" lines from editable text now that details render in a fixed summary box.
 */
function btb_email_template_strip_autogenerated_detail_lines(string $text): string
{
    if ($text === '') {
        return '';
    }
    $lines = preg_split('/\r\n|\r|\n/', $text);
    if (!is_array($lines)) {
        return $text;
    }
    $out = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*[^:\n]+:\s*.+\{\{\s*(booking\.|after\.|user\.|login_meta\.|chat\.|reason|payment_intent_id)\s*\}\}/', $line)) {
            continue;
        }
        $out[] = $line;
    }

    return trim(implode("\n", $out));
}

function btb_email_template_structured_block_title(string $templateKey): string
{
    if ($templateKey === 'guest_bookings_digest_guest') {
        return 'Summary';
    }
    if ($templateKey === 'user_register_welcome') {
        return 'Account details';
    }
    if ($templateKey === 'guest_chat_staff_reply_guest') {
        return 'Recent messages';
    }
    if ($templateKey === 'host_chat_guest_message_host') {
        return 'Latest message';
    }
    if (strpos($templateKey, '_host') !== false) {
        return 'Booking details';
    }
    if (strpos($templateKey, 'massage_') === 0 || strpos($templateKey, 'user_') === 0) {
        return 'Booking details';
    }

    return 'Booking details';
}

/** Wellness service line: "Type (N min)" */
function btb_email_template_wellness_service_line(?array $row): string
{
    if ($row === null) {
        return '';
    }
    $dur = btb_email_tpl_cell($row, 'duration');
    $svc = btb_email_tpl_cell($row, 'massage_type');
    if ($svc === '') {
        return '';
    }

    return $dur !== '' ? ($svc . ' (' . $dur . ' min)') : $svc;
}

/** Append Check-in / Check-out as separate rows (room stays). */
function btb_email_template_append_room_stay_date_rows(array &$rows, ?array $row): void
{
    if ($row === null || !is_array($row)) {
        return;
    }
    $in = btb_email_tpl_cell($row, 'checkin_date');
    $out = btb_email_tpl_cell($row, 'checkout_date');
    if ($in !== '') {
        $rows[] = ['Check-in', $in];
    }
    if ($out !== '') {
        $rows[] = ['Check-out', $out];
    }
}

/** Append wellness appointment date and time on separate rows. */
function btb_email_template_append_wellness_date_time_rows(array &$rows, ?array $row): void
{
    if ($row === null || !is_array($row)) {
        return;
    }
    $d = btb_email_tpl_cell($row, 'massage_date');
    $t = btb_email_tpl_cell($row, 'massage_time');
    if ($d !== '') {
        $rows[] = ['Date', $d];
    }
    if ($t !== '') {
        $rows[] = ['Time', $t];
    }
}

function btb_email_template_guests_and_pets_line(?array $row): string
{
    if ($row === null) {
        return '';
    }
    $g = btb_email_tpl_cell($row, 'guests_count');
    $p = btb_email_tpl_cell($row, 'pets');
    $parts = [];
    if ($g !== '') {
        $parts[] = $g === '1' ? '1 guest' : ($g . ' guests');
    }
    if ($p !== '' && (int) $p > 0) {
        $parts[] = ((int) $p === 1) ? '1 dog' : ($p . ' dogs');
    }

    return implode(' · ', $parts);
}

/**
 * Status badge + plain status line share the same label text.
 *
 * @return array{label:string,bg:string,color:string}|null
 */
function btb_email_template_status_badge_meta(string $templateKey): ?array
{
    static $map = [
        'booking_confirmation_guest' => ['label' => 'Awaiting confirmation', 'bg' => '#fef3c7', 'color' => '#92400e'],
        'guest_bookings_digest_guest' => ['label' => 'Summary', 'bg' => '#e0f2fe', 'color' => '#0369a1'],
        'guest_payment_succeeded_guest' => ['label' => 'Paid', 'bg' => '#d1fae5', 'color' => '#065f46'],
        'booking_cancelled_guest' => ['label' => 'Cancelled', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        'room_booking_updated_guest' => ['label' => 'Updated', 'bg' => '#f3f4f6', 'color' => '#374151'],
        'massage_booking_guest' => ['label' => 'Awaiting confirmation', 'bg' => '#fef3c7', 'color' => '#92400e'],
        'massage_booking_updated_guest' => ['label' => 'Updated', 'bg' => '#f3f4f6', 'color' => '#374151'],
        'user_register_welcome' => ['label' => 'Welcome', 'bg' => '#e0e7ff', 'color' => '#3730a3'],
        'booking_request_host' => ['label' => 'Action required', 'bg' => '#fef3c7', 'color' => '#92400e'],
        'booking_confirmed_host' => ['label' => 'Awaiting guest payment', 'bg' => '#dbeafe', 'color' => '#1e40af'],
        'booking_cancelled_host' => ['label' => 'Cancelled', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        'booking_payment_succeeded_host' => ['label' => 'Confirmed', 'bg' => '#d1fae5', 'color' => '#065f46'],
        'massage_booking_host' => ['label' => 'New request', 'bg' => '#fef3c7', 'color' => '#92400e'],
        'massage_booking_confirmed_host' => ['label' => 'Confirmed', 'bg' => '#d1fae5', 'color' => '#065f46'],
        'massage_booking_cancelled_host' => ['label' => 'Cancelled', 'bg' => '#fee2e2', 'color' => '#991b1b'],
        'massage_payment_succeeded_host' => ['label' => 'Paid', 'bg' => '#d1fae5', 'color' => '#065f46'],
        'room_booking_updated_host' => ['label' => 'Updated', 'bg' => '#f3f4f6', 'color' => '#374151'],
        'massage_booking_updated_host' => ['label' => 'Updated', 'bg' => '#f3f4f6', 'color' => '#374151'],
        'host_chat_guest_message_host' => ['label' => 'Guest message', 'bg' => '#e0f2fe', 'color' => '#0369a1'],
        'guest_chat_staff_reply_guest' => ['label' => 'New reply', 'bg' => '#dcfce7', 'color' => '#166534'],
    ];

    return $map[$templateKey] ?? null;
}

function btb_email_template_status_badge_html(?array $meta): string
{
    if ($meta === null || trim((string) ($meta['label'] ?? '')) === '') {
        return '';
    }
    $label = htmlspecialchars(trim((string) $meta['label']), ENT_QUOTES, 'UTF-8');
    $bg = htmlspecialchars((string) ($meta['bg'] ?? '#f3f4f6'), ENT_QUOTES, 'UTF-8');
    $fg = htmlspecialchars((string) ($meta['color'] ?? '#374151'), ENT_QUOTES, 'UTF-8');

    return '<div class="btb-email-badge-wrap" style="margin:0 0 16px;">'
        . '<span class="btb-email-badge" style="display:inline-block;padding:6px 12px;border-radius:999px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.02em;background:' . $bg . ';color:' . $fg . ';">'
        . $label . '</span></div>';
}

/**
 * Detail rows for the booking card (label → value), in display order.
 *
 * @return array<int, array{0:string,1:string}>
 */
function btb_email_template_booking_card_rows(string $templateKey, array $vars): array
{
    $b = isset($vars['booking']) && is_array($vars['booking']) ? $vars['booking'] : [];
    $a = isset($vars['after']) && is_array($vars['after']) ? $vars['after'] : [];
    $reason = trim((string) ($vars['reason'] ?? ''));
    $pay = trim((string) ($vars['payment_intent_id'] ?? ''));

    $roomFlowGuest = static function (array $row): array {
        $rows = [];
        $rn = btb_email_tpl_cell($row, 'room_name');
        if ($rn !== '') {
            $rows[] = ['Room', $rn];
        }
        btb_email_template_append_room_stay_date_rows($rows, $row);
        $guests = btb_email_template_guests_and_pets_line($row);
        if ($guests !== '') {
            $rows[] = ['Guests', $guests];
        }
        $code = btb_email_tpl_cell($row, 'confirmation_code');
        if ($code !== '') {
            $rows[] = ['Confirmation code', $code];
        }

        return $rows;
    };

    $wellnessGuest = static function (array $row): array {
        $rows = [];
        $svc = btb_email_template_wellness_service_line($row);
        if ($svc !== '') {
            $rows[] = ['Room', $svc];
        }
        btb_email_template_append_wellness_date_time_rows($rows, $row);
        $rows[] = ['Guests', 'Wellness booking'];
        $code = btb_email_tpl_cell($row, 'confirmation_code');
        if ($code !== '') {
            $rows[] = ['Confirmation code', $code];
        }

        return $rows;
    };

    switch ($templateKey) {
        case 'booking_confirmation_guest':
        case 'booking_confirmed_guest':
            return $roomFlowGuest($b);
        case 'guest_payment_succeeded_guest':
        case 'booking_payment_succeeded_guest':
        case 'massage_payment_succeeded_guest':
            $svcLine = btb_email_template_wellness_service_line($b);
            $rn = btb_email_tpl_cell($b, 'room_name');
            if ($svcLine !== '' && $rn === '') {
                $rows = $wellnessGuest($b);
            } else {
                $rows = $roomFlowGuest($b);
            }
            $tot = btb_email_tpl_cell($b, 'total_amount');
            $cur = btb_email_tpl_cell($b, 'currency');
            $amt = ($tot !== '' && $cur !== '') ? ($tot . ' ' . $cur) : $tot;
            if ($amt !== '') {
                $rows[] = ['Total paid', $amt];
            }
            if ($pay !== '') {
                $rows[] = ['Payment reference', $pay];
            }

            return $rows;
        case 'booking_cancelled_guest':
            $rows = $roomFlowGuest($b);
            if ($reason !== '') {
                $rows[] = ['Reason', $reason];
            }

            return $rows;
        case 'room_booking_updated_guest':
            return $roomFlowGuest($a);
        case 'massage_booking_guest':
            return $wellnessGuest($b);
        case 'massage_booking_updated_guest':
            return $wellnessGuest($a);
        case 'user_register_welcome':
            $u = isset($vars['user']) && is_array($vars['user']) ? $vars['user'] : [];

            return [
                ['Name', btb_email_tpl_cell($u, 'name')],
                ['Email', btb_email_tpl_cell($u, 'email')],
            ];
        case 'booking_request_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
                ['Email', btb_email_tpl_cell($b, 'email')],
                ['Phone', btb_email_tpl_cell($b, 'phone')],
            ];
            $rn = btb_email_tpl_cell($b, 'room_name');
            if ($rn !== '') {
                $rows[] = ['Room', $rn];
            }
            btb_email_template_append_room_stay_date_rows($rows, $b);
            $guests = btb_email_template_guests_and_pets_line($b);
            if ($guests !== '') {
                $rows[] = ['Guests', $guests];
            }
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }

            return $rows;
        case 'booking_confirmed_host':
        case 'booking_cancelled_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
            ];
            $rn = btb_email_tpl_cell($b, 'room_name');
            if ($rn !== '') {
                $rows[] = ['Room', $rn];
            }
            btb_email_template_append_room_stay_date_rows($rows, $b);
            $guests = btb_email_template_guests_and_pets_line($b);
            if ($guests !== '') {
                $rows[] = ['Guests', $guests];
            }
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }
            if ($reason !== '' && $templateKey === 'booking_cancelled_host') {
                $rows[] = ['Reason', $reason];
            }

            return $rows;
        case 'booking_payment_succeeded_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
            ];
            $rn = btb_email_tpl_cell($b, 'room_name');
            if ($rn !== '') {
                $rows[] = ['Room', $rn];
            }
            btb_email_template_append_room_stay_date_rows($rows, $b);
            $guests = btb_email_template_guests_and_pets_line($b);
            if ($guests !== '') {
                $rows[] = ['Guests', $guests];
            }
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }
            $tot = btb_email_tpl_cell($b, 'total_amount');
            $cur = btb_email_tpl_cell($b, 'currency');
            $amt = ($tot !== '' && $cur !== '') ? ($tot . ' ' . $cur) : $tot;
            if ($amt !== '') {
                $rows[] = ['Total', $amt];
            }
            if ($pay !== '') {
                $rows[] = ['Payment reference', $pay];
            }

            return $rows;
        case 'massage_booking_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
                ['Email', btb_email_tpl_cell($b, 'email')],
                ['Phone', btb_email_tpl_cell($b, 'phone')],
            ];
            $svc = btb_email_template_wellness_service_line($b);
            if ($svc !== '') {
                $rows[] = ['Room', $svc];
            }
            btb_email_template_append_wellness_date_time_rows($rows, $b);
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }

            return $rows;
        case 'massage_booking_confirmed_host':
        case 'massage_booking_cancelled_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
            ];
            $svc = btb_email_template_wellness_service_line($b);
            if ($svc !== '') {
                $rows[] = ['Room', $svc];
            }
            btb_email_template_append_wellness_date_time_rows($rows, $b);
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }
            if ($reason !== '' && $templateKey === 'massage_booking_cancelled_host') {
                $rows[] = ['Reason', $reason];
            }

            return $rows;
        case 'massage_payment_succeeded_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($b, 'guest_name')],
            ];
            $svc = btb_email_template_wellness_service_line($b);
            if ($svc !== '') {
                $rows[] = ['Room', $svc];
            }
            btb_email_template_append_wellness_date_time_rows($rows, $b);
            $code = btb_email_tpl_cell($b, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }
            $tot = btb_email_tpl_cell($b, 'total_amount');
            $cur = btb_email_tpl_cell($b, 'currency');
            $amt = ($tot !== '' && $cur !== '') ? ($tot . ' ' . $cur) : $tot;
            if ($amt !== '') {
                $rows[] = ['Total', $amt];
            }
            if ($pay !== '') {
                $rows[] = ['Payment reference', $pay];
            }

            return $rows;
        case 'room_booking_updated_host':
            $rows = [
                ['Guest', btb_email_tpl_cell($a, 'guest_name')],
            ];
            $rn = btb_email_tpl_cell($a, 'room_name');
            if ($rn !== '') {
                $rows[] = ['Room', $rn];
            }
            btb_email_template_append_room_stay_date_rows($rows, $a);
            $guests = btb_email_template_guests_and_pets_line($a);
            if ($guests !== '') {
                $rows[] = ['Guests', $guests];
            }
            $code = btb_email_tpl_cell($a, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }

            return $rows;
        case 'massage_booking_updated_host':
            $row = $a;
            $rows = [
                ['Guest', btb_email_tpl_cell($row, 'guest_name')],
            ];
            $svc = btb_email_template_wellness_service_line($row);
            if ($svc !== '') {
                $rows[] = ['Room', $svc];
            }
            btb_email_template_append_wellness_date_time_rows($rows, $row);
            $code = btb_email_tpl_cell($row, 'confirmation_code');
            if ($code !== '') {
                $rows[] = ['Confirmation code', $code];
            }

            return $rows;
        case 'host_chat_guest_message_host':
            $c = isset($vars['chat']) && is_array($vars['chat']) ? $vars['chat'] : [];

            return [
                ['From guest', btb_email_tpl_cell($c, 'message_preview')],
            ];
        case 'guest_chat_staff_reply_guest':
            $c = isset($vars['chat']) && is_array($vars['chat']) ? $vars['chat'] : [];
            $recent = isset($c['recent_messages']) && is_array($c['recent_messages']) ? $c['recent_messages'] : [];
            if ($recent !== []) {
                $staffLbl = function_exists('btb_chat_staff_from_label') ? btb_chat_staff_from_label() : 'Back to Base';
                $rows = [];
                foreach ($recent as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $snd = (string) ($m['sender'] ?? '');
                    $body = trim((string) ($m['body'] ?? ''));
                    if ($body === '') {
                        continue;
                    }
                    if (function_exists('mb_strlen') && mb_strlen($body) > 800) {
                        $body = mb_substr($body, 0, 800) . '…';
                    } elseif (strlen($body) > 800) {
                        $body = substr($body, 0, 800) . '…';
                    }
                    $label = ($snd === 'staff') ? $staffLbl : 'You';

                    $rows[] = [$label, $body];
                }

                return $rows !== [] ? $rows : [['Latest reply', btb_email_tpl_cell($c, 'message_preview')]];
            }

            return [
                ['Latest reply', btb_email_tpl_cell($c, 'message_preview')],
            ];
        default:
            return [];
    }
}

function btb_email_template_header_logo_html(): string
{
    $raw = defined('BTB_EMAIL_HEADER_LOGO_URL') ? (string) BTB_EMAIL_HEADER_LOGO_URL : '';
    $abs = btb_email_safe_public_url(trim($raw));
    // Wordmark: padding-bottom matches descenders to optical bottom with the PNG (email clients vary on valign bottom).
    $wordmark = '<span style="font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,sans-serif;font-size:28px;font-weight:700;color:#111827;letter-spacing:0.3px;line-height:1;display:inline-block;padding-bottom:3px;">Back to Base</span>';
    if ($abs === '') {
        return '<div class="btb-email-header" style="margin:0 0 32px;padding:16px 0 0;text-align:center;">' . $wordmark . '</div>';
    }
    $src = htmlspecialchars($abs, ENT_QUOTES, 'UTF-8');

    return '<div class="btb-email-header" style="margin:0 0 32px;padding:16px 0 0;text-align:center;">'
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;border-collapse:collapse;">'
        . '<tr><td style="vertical-align:bottom;padding:2px 14px 0 0;line-height:0;">'
        . '<img src="' . $src . '" alt="" height="44" style="display:block;height:44px;width:auto;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;vertical-align:bottom;" />'
        . '</td><td style="vertical-align:bottom;padding:0 0 1px 0;line-height:0;">' . $wordmark . '</td></tr></table>'
        . '</div>';
}

/** Host notification templates use suffix `_host` (e.g. booking_request_host). */
function btb_email_template_key_is_host(string $templateKey): bool
{
    $t = trim($templateKey);

    return $t !== '' && substr($t, -5) === '_host';
}

/** Resolved https URL for the admin panel in host notification emails. */
function btb_email_template_admin_panel_href(array $vars): string
{
    $u = trim((string) ($vars['admin_url'] ?? ''));
    if ($u === '' && defined('ADMIN_BOOKINGS_URL')) {
        $u = (string) ADMIN_BOOKINGS_URL;
    }
    if ($u === '') {
        $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
        $u = $site !== '' ? $site . '/admin.html' : '';
    }
    if ($u === '') {
        return '';
    }
    $norm = btb_email_safe_public_url($u);

    return $norm !== '' ? $norm : '';
}

/** Primary site-style blue CTA block (matches digest / guest chat buttons). */
function btb_email_template_primary_blue_cta_html(string $labelPlain, string $absoluteHref): string
{
    $absoluteHref = trim($absoluteHref);
    if ($absoluteHref === '' || trim($labelPlain) === '') {
        return '';
    }
    $ctaLabelR = htmlspecialchars($labelPlain, ENT_QUOTES, 'UTF-8');
    $ctaHrefEsc = htmlspecialchars($absoluteHref, ENT_QUOTES, 'UTF-8');

    return '<div class="btb-email-primary-cta-wrap" style="margin:26px 0 10px;text-align:center;">'
        . '<a class="btb-email-digest-cta" href="' . $ctaHrefEsc . '" style="display:inline-block;padding:14px 28px;background-color:#2563eb;color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;text-decoration:none!important;border-radius:8px;font-weight:600;font-size:15px;border:2px solid #1d4ed8;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">'
        . $ctaLabelR . '</a></div>';
}

/**
 * Target href for the fixed "View booking" footer button (guest: booking or dashboard; host: admin).
 */
function btb_email_template_footer_view_booking_href(array $vars, string $templateKey): string
{
    $tk = $templateKey;
    if ($tk !== '' && btb_email_template_key_is_host($tk)) {
        return btb_email_template_admin_panel_href($vars);
    }
    $book = trim((string) ($vars['booking_url'] ?? ''));
    if ($book !== '') {
        $norm = btb_email_safe_public_url($book);

        return $norm !== '' ? $norm : '';
    }
    $dash = trim((string) ($vars['dashboard_url'] ?? ''));
    if ($dash !== '') {
        $norm = btb_email_safe_public_url($dash);

        return $norm !== '' ? $norm : '';
    }
    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';

    return $site !== '' ? btb_email_safe_public_url($site . '/') : '';
}

/**
 * Footer band: How to Find Us + View booking (outline style). Omitted for selected guest templates and all `*_host` templates.
 */
function btb_email_template_mail_footer_band_html(array $vars, string $templateKey, string $cardHex = '#ffffff'): string
{
    $tk = trim($templateKey);
    static $omitFooterBand = [
        'guest_bookings_digest_guest',
        'guest_payment_succeeded_guest',
        'booking_confirmation_guest',
        'massage_booking_guest',
    ];
    if ($tk !== '' && in_array($tk, $omitFooterBand, true)) {
        return '';
    }
    if (btb_email_template_key_is_host($templateKey)) {
        return '';
    }

    $bridgeBg = function_exists('btb_email_sanitize_hex_color')
        ? btb_email_sanitize_hex_color($cardHex, '#ffffff')
        : '#ffffff';
    $bridgeBgEsc = htmlspecialchars($bridgeBg, ENT_QUOTES, 'UTF-8');

    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    $aboutRaw = defined('BTB_EMAIL_ABOUT_URL') ? (string) BTB_EMAIL_ABOUT_URL : $site . '/about.php';
    $aboutHref = htmlspecialchars(btb_email_safe_public_url($aboutRaw), ENT_QUOTES, 'UTF-8');

    $viewRaw = btb_email_template_footer_view_booking_href($vars, $templateKey);
    if ($viewRaw === '') {
        $viewRaw = btb_email_safe_public_url($site !== '' ? $site . '/' : '');
    }
    $viewHref = htmlspecialchars($viewRaw, ENT_QUOTES, 'UTF-8');

    $btn = 'display:block;width:100%;box-sizing:border-box;text-align:center;padding:14px 18px;background:#ffffff !important;color:#0f172a !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;border:2px solid #cbd5e1 !important;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;';

    $btnRow = '<table role="presentation" class="btb-email-cta-wrap" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;border-collapse:collapse;width:100%;">'
        . '<tr>'
        . '<td class="btb-email-cta-cell" width="50%" style="width:50%;padding:6px 6px;vertical-align:top;">'
        . '<a class="btb-email-cta-btn" href="' . $aboutHref . '" style="' . $btn . '">How to Find Us</a>'
        . '</td>'
        . '<td class="btb-email-cta-cell" width="50%" style="width:50%;padding:6px 6px;vertical-align:top;">'
        . '<a class="btb-email-cta-btn" href="' . $viewHref . '" style="' . $btn . '">View booking</a>'
        . '</td>'
        . '</tr></table>';

    return '<table role="presentation" class="btb-email-footer-bridge" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;background:' . $bridgeBgEsc . ';">'
        . '<tr><td class="btb-email-footer-bridge-inner" style="padding:28px 20px 26px;background:' . $bridgeBgEsc . ';">' . $btnRow . '</td></tr>'
        . '</table>';
}

function btb_email_template_merge_default_placeholders(array $vars): array
{
    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    $dash = defined('GUEST_DASHBOARD_URL') ? (string) GUEST_DASHBOARD_URL : ($site !== '' ? $site . '/dashboard.html' : '');
    if (!isset($vars['dashboard_url']) || trim((string) $vars['dashboard_url']) === '') {
        $vars['dashboard_url'] = $dash;
    }
    $about = defined('BTB_EMAIL_ABOUT_URL') ? (string) BTB_EMAIL_ABOUT_URL : ($site !== '' ? $site . '/about.php' : '');
    $explore = defined('BTB_EMAIL_EXPLORE_URL') ? (string) BTB_EMAIL_EXPLORE_URL : ($site !== '' ? $site . '/explore.php' : '');
    if (!isset($vars['about_url']) || trim((string) $vars['about_url']) === '') {
        $vars['about_url'] = $about;
    }
    if (!isset($vars['explore_url']) || trim((string) $vars['explore_url']) === '') {
        $vars['explore_url'] = $explore;
    }
    $messagesPage = defined('BTB_MESSAGES_PAGE_URL') ? trim((string) BTB_MESSAGES_PAGE_URL) : ($site !== '' ? $site . '/messages.html' : '');
    if ($messagesPage !== '' && (!isset($vars['messages_url']) || trim((string) $vars['messages_url']) === '')) {
        $vars['messages_url'] = btb_email_safe_public_url($messagesPage);
    }

    return $vars;
}

/**
 * Prominent guest message block for host booking-request emails (separate from booking details card).
 */
/**
 * Booking-details card (label/value table) — shared by template summaries and guest digest.
 *
 * @param array<int, array{0:string,1:string}> $pairs
 */
function btb_email_template_build_details_card_html(
    string $cardTitle,
    array $pairs,
    ?array $badgeMeta = null,
    string $marginStyle = 'margin:26px 0',
    bool $highlightLastRow = false,
    ?array $highlightMeta = null
): string {
    $badgeHtml = btb_email_template_status_badge_html($badgeMeta);
    $rows = [];
    foreach ($pairs as $pair) {
        $label = (string) ($pair[0] ?? '');
        $val = trim((string) ($pair[1] ?? ''));
        if ($val === '') {
            continue;
        }
        $rows[] = ['label' => $label, 'val' => $val];
    }
    $rowsHtml = '';
    $n = count($rows);
    $hiMeta = ($highlightLastRow && is_array($highlightMeta)) ? $highlightMeta : null;
    $hiBg = $hiMeta !== null && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($hiMeta['bg'] ?? ''))
        ? (string) $hiMeta['bg'] : '#dcfce7';
    $hiFg = $hiMeta !== null && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($hiMeta['color'] ?? ''))
        ? (string) $hiMeta['color'] : '#166534';
    $hiBgEsc = htmlspecialchars($hiBg, ENT_QUOTES, 'UTF-8');
    $hiFgEsc = htmlspecialchars($hiFg, ENT_QUOTES, 'UTF-8');
    foreach ($rows as $i => $row) {
        $label = $row['label'];
        $val = $row['val'];
        $isLast = ($i === $n - 1);
        $l = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $vEsc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        $v = strpos($val, "\n") !== false ? nl2br($vEsc) : $vEsc;
        if ($highlightLastRow && $isLast && $hiMeta !== null) {
            $rowsHtml .= '<tr><td class="btb-email-details-label" style="padding:10px 14px 10px 12px;color:' . $hiFgEsc . ';font-size:13px;vertical-align:top;white-space:nowrap;font-weight:600;background:' . $hiBgEsc . ';border-radius:8px 0 0 8px;">' . $l
                . '</td><td class="btb-email-details-value" style="padding:10px 12px 10px 0;color:' . $hiFgEsc . ';font-size:15px;font-weight:500;vertical-align:top;background:' . $hiBgEsc . ';border-radius:0 8px 8px 0;">' . $v . '</td></tr>';
        } else {
            $rowsHtml .= '<tr><td class="btb-email-details-label" style="padding:6px 14px 6px 0;color:#475569;font-size:13px;vertical-align:top;white-space:nowrap;font-weight:500;">' . $l
                . '</td><td class="btb-email-details-value" style="padding:6px 0;color:#1e293b;font-size:15px;font-weight:500;vertical-align:top;">' . $v . '</td></tr>';
        }
    }
    if ($badgeHtml === '' && $rowsHtml === '') {
        return '';
    }
    $title = htmlspecialchars(trim($cardTitle), ENT_QUOTES, 'UTF-8');
    $margin = trim($marginStyle) !== '' ? $marginStyle : 'margin:26px 0';

    return '<div class="btb-email-details" style="' . $margin . ';padding:20px 22px;background:#f8fafc;border:1px solid #e8eef3;border-radius:12px;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">'
        . ($title !== '' ? '<div class="btb-email-details-title" style="font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#5c6b7a;margin:0 0 14px;">' . $title . '</div>' : '')
        . $badgeHtml
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">'
        . $rowsHtml
        . '</table></div>';
}

function btb_email_template_build_guest_message_card_html(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }
    $bodyFont = 'font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;';
    $vEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $vHtml = strpos($message, "\n") !== false ? nl2br($vEsc) : $vEsc;

    return '<div class="btb-email-guest-message" style="margin:26px 0;padding:20px 22px;background:#e0f2fe;border:1px solid #bae6fd;border-radius:12px;' . $bodyFont . '">'
        . '<div style="font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#0369a1;margin:0 0 12px;">Message from guest</div>'
        . '<div style="font-size:15px;line-height:1.65;color:#0f172a;font-weight:500;">' . $vHtml . '</div>'
        . '</div>';
}

function btb_email_template_booking_guest_message_from_vars(array $vars): string
{
    $direct = trim((string) ($vars['booking_guest_message'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }
    $b = isset($vars['booking']) && is_array($vars['booking']) ? $vars['booking'] : [];

    return trim((string) ($b['guest_message'] ?? $b['special_requests'] ?? ''));
}

function btb_email_template_build_structured_summary_html(string $templateKey, array $vars): string
{
    $meta = btb_email_template_status_badge_meta($templateKey);
    $pairs = btb_email_template_booking_card_rows($templateKey, $vars);
    $highlightLast = ($templateKey === 'guest_chat_staff_reply_guest');

    return btb_email_template_build_details_card_html(
        btb_email_template_structured_block_title($templateKey),
        $pairs,
        $meta,
        'margin:26px 0',
        $highlightLast,
        $meta
    );
}

/**
 * Sample variables for admin preview (no real guest data).
 *
 * @return array<string,mixed>
 */
function btb_email_template_preview_sample_vars(string $templateKey): array
{
    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : 'https://backtobase.example';
    $booking = [
        'id' => '42',
        'guest_name' => 'Dmitriy',
        'email' => 'guest@example.com',
        'phone' => '+1 250-691-1118',
        'room_name' => 'The Nouk',
        'checkin_date' => '2026-06-01',
        'checkout_date' => '2026-06-05',
        'guests_count' => '2',
        'pets' => '1',
        'confirmation_code' => '142-412',
        'massage_type' => 'Relaxation massage',
        'duration' => '60',
        'massage_date' => '2026-06-02',
        'massage_time' => '14:00',
        'total_amount' => '320.00',
        'currency' => 'CAD',
    ];
    $after = array_merge($booking, [
        'room_name' => 'The Nouk',
        'checkin_date' => '2026-06-02',
        'checkout_date' => '2026-06-06',
    ]);
    $before = array_merge($booking, [
        'room_name' => 'Kelder suite',
        'checkin_date' => '2026-06-01',
        'checkout_date' => '2026-06-04',
    ]);

    $vars = [
        'booking' => $booking,
        'after' => $after,
        'before' => $before,
        'user' => ['name' => 'Dmitriy', 'email' => 'guest@example.com'],
        'reason' => 'Dates no longer available',
        'payment_intent_id' => 'pi_demo_001',
        'pay_url' => $site . '/order.html?pay=room&booking_id=42&confirmation_code=142-412',
        'booking_url' => $site . '/booking-confirmation.html?code=DEMO',
        'dashboard_url' => $site . '/dashboard.html',
        'messages_url' => $site . '/messages.html',
        'admin_url' => $site . '/admin.html',
        'site_url' => $site,
        'confirmation_code' => '142-412',
        'chat' => [
            'message_preview' => "Hello!\n\nCould we check in around 4:00 pm?\n\nThanks!",
            'heading_line' => 'You have a new message from a guest.',
            'staff_from' => 'Rob at Back to Base',
        ],
    ];

    if ($templateKey === 'guest_bookings_digest_guest') {
        $vars['digest_inner_html'] = btb_guest_digest_preview_sample_inner_html();
    }
    if ($templateKey === 'guest_chat_staff_reply_guest') {
        $sf = function_exists('btb_chat_staff_from_label') ? btb_chat_staff_from_label() : 'Rob at Back to Base';
        $vars['chat']['staff_from'] = $sf;
        $vars['chat']['heading_line'] = 'You have a new message from ' . $sf . '.';
        $vars['chat']['recent_messages'] = [
            ['sender' => 'guest', 'body' => "Hi — could we request a late checkout on Sunday?\n\nThanks!"],
            ['sender' => 'staff', 'body' => "Hi! Late checkout depends on the next guest. We'll confirm by Saturday."],
            ['sender' => 'guest', 'body' => 'Sounds good — we can leave by 11 if needed.'],
            ['sender' => 'staff', 'body' => "We've confirmed 12:00 checkout for you. Enjoy your stay!"],
        ];
    }

    return $vars;
}

/**
 * Merge unsaved editor fields from POST into a template row for preview.
 *
 * @param array<string,string> $post
 * @return array<string,mixed>
 */
function btb_email_template_preview_row_from_post(string $templateKey, array $post): array
{
    global $conn;
    $base = ['template_key' => $templateKey];
    if ($conn && function_exists('btb_email_template_get_one')) {
        $one = btb_email_template_get_one($conn, $templateKey);
        if (is_array($one)) {
            $base = array_merge($base, $one);
        }
    }

    $pick = static function (array $p, string $k, $fallback) {
        if (!array_key_exists($k, $p)) {
            return $fallback;
        }

        return (string) $p[$k];
    };

    return array_merge($base, [
        'template_key' => $templateKey,
        'subject' => $pick($post, 'subject', (string) ($base['subject'] ?? '')),
        'heading' => $pick($post, 'heading', (string) ($base['heading'] ?? '')),
        'body' => array_key_exists('body', $post) ? (string) $post['body'] : (string) ($base['body'] ?? ''),
        'body_after' => array_key_exists('body_after', $post) ? (string) $post['body_after'] : (string) ($base['body_after'] ?? ''),
        'body_contact' => array_key_exists('body_contact', $post) ? (string) $post['body_contact'] : (string) ($base['body_contact'] ?? ''),
        'cta_label' => $pick($post, 'cta_label', (string) ($base['cta_label'] ?? '')),
        'cta_url' => $pick($post, 'cta_url', (string) ($base['cta_url'] ?? '')),
        'image_url' => $pick($post, 'image_url', (string) ($base['image_url'] ?? '')),
    ]);
}

/**
 * Embedded CSS for v2 shell: force light appearance in OS/app dark mode + stack footer CTAs on narrow screens.
 */
function btb_email_template_v2_embedded_css(string $outerHex, string $cardHex): string
{
    $o = preg_match('/^#[0-9a-fA-F]{3,8}$/', $outerHex) ? $outerHex : '#ffffff';
    $c = preg_match('/^#[0-9a-fA-F]{3,8}$/', $cardHex) ? $cardHex : '#ffffff';

    return '<style type="text/css">'
        . ':root{color-scheme:light only;}'
        . 'html.btb-email-html{color-scheme:light only;}'
        . 'a.btb-email-digest-cta{background-color:#2563eb!important;color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;border-color:#1d4ed8!important;text-decoration:none!important;}'
        . '@media (prefers-color-scheme:dark){'
        . 'html.btb-email-html,body.btb-email-body{background-color:' . $o . '!important;color:#111827!important;-webkit-text-fill-color:#111827!important;}'
        . 'table.btb-email-outer,td.btb-email-outer-cell{background-color:' . $o . '!important;}'
        . 'table.btb-email-card,td.btb-email-card-inner{background-color:' . $c . '!important;color:#111827!important;-webkit-text-fill-color:#111827!important;}'
        . 'td.btb-email-footer-bridge-inner,.btb-email-footer-bridge{background:' . $c . '!important;background-color:' . $c . '!important;}'
        . '.btb-email-heading{color:#1e293b!important;-webkit-text-fill-color:#1e293b!important;}'
        . '.btb-email-bodytext,.btb-email-bodycontact,.btb-email-header{color:#111827!important;-webkit-text-fill-color:#111827!important;}'
        . '.btb-email-details{background-color:#f8fafc!important;border-color:#e8eef3!important;color:#111827!important;}'
        . '.btb-email-details-title{color:#5c6b7a!important;-webkit-text-fill-color:#5c6b7a!important;}'
        . '.btb-email-details-label{color:#475569!important;-webkit-text-fill-color:#475569!important;}'
        . '.btb-email-details-value{color:#1e293b!important;-webkit-text-fill-color:#1e293b!important;}'
        . '.btb-email-footer-hero-contacts,.btb-email-footer-hero-contacts a,.btb-email-footer-hero-contacts span{color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;}'
        . 'a.btb-email-digest-cta{background-color:#2563eb!important;color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;border-color:#1d4ed8!important;text-decoration:none!important;}'
        . 'a.btb-email-cta-btn{background-color:' . $c . '!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;border-color:#cbd5e1!important;}'
        . '}'
        . '[data-ogsc] html.btb-email-html,[data-ogsc] body.btb-email-body{background-color:' . $o . '!important;color:#111827!important;}'
        . '[data-ogsc] table.btb-email-card,[data-ogsc] td.btb-email-card-inner{background-color:' . $c . '!important;color:#111827!important;}'
        . '[data-ogsc] .btb-email-details{background-color:#f8fafc!important;border-color:#e8eef3!important;}'
        . '[data-ogsc] .btb-email-footer-hero-contacts,[data-ogsc] .btb-email-footer-hero-contacts a,[data-ogsc] .btb-email-footer-hero-contacts span{color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;}'
        . '[data-ogsc] a.btb-email-digest-cta{background-color:#2563eb!important;color:#ffffff!important;-webkit-text-fill-color:#ffffff!important;border-color:#1d4ed8!important;}'
        . '.btb-email-primary-cta-wrap a.btb-email-digest-cta{display:inline-block!important;padding:14px 28px!important;border-radius:8px!important;font-weight:600!important;font-size:15px!important;}'
        . '[data-ogsc] a.btb-email-cta-btn{background-color:' . $c . '!important;color:#0f172a!important;border-color:#cbd5e1!important;}'
        . '@media screen and (max-width:600px){'
        . '.btb-email-cta-cell{display:block!important;width:100%!important;max-width:100%!important;padding:8px 0!important;}'
        . '.btb-email-cta-wrap{width:100%!important;}'
        . 'a.btb-email-cta-btn{max-width:100%!important;}'
        . '}'
        . '</style>';
}

function btbEmailTemplateRenderFromAdminRow(array $tplRow, array $vars, string $fallbackHtml): string
{
    $vars = btb_email_template_merge_default_placeholders($vars);
    $templateKey = trim((string) ($tplRow['template_key'] ?? ''));

    $heading = trim((string) ($tplRow['heading'] ?? ''));
    $body = (string) ($tplRow['body'] ?? '');
    $bodyAfter = (string) ($tplRow['body_after'] ?? '');
    $bodyContact = (string) ($tplRow['body_contact'] ?? '');
    $ctaLabel = trim((string) ($tplRow['cta_label'] ?? ''));
    $ctaUrlRaw = (string) ($tplRow['cta_url'] ?? '');
    $imgUrlRaw = (string) ($tplRow['image_url'] ?? '');

    $digestInner = '';
    if ($templateKey === 'guest_bookings_digest_guest') {
        $digestInner = trim((string) ($vars['digest_inner_html'] ?? ''));
        if ($digestInner !== '' && $heading === '') {
            $heading = btb_guest_digest_default_heading();
        }
    }

    if ($digestInner === '' && $heading === '' && trim($body) === '' && trim($bodyAfter) === '' && trim($bodyContact) === '' && $ctaLabel === '' && trim($ctaUrlRaw) === '' && trim($imgUrlRaw) === '') {
        return $fallbackHtml;
    }

    global $conn;
    $br = ($conn && function_exists('btb_email_branding_api_data'))
        ? btb_email_branding_api_data($conn)
        : (function_exists('btb_email_branding_defaults') ? btb_email_branding_defaults() : []);
    $outerHex = btb_email_sanitize_hex_color($br['outer_background'] ?? '', '#ffffff');
    $cardHex = btb_email_sanitize_hex_color($br['card_background'] ?? '', '#ffffff');
    $outer = htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8');
    $card = htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8');

    $bodyClean = btb_email_template_strip_autogenerated_detail_lines($body);
    $bodyAfterClean = btb_email_template_strip_autogenerated_detail_lines($bodyAfter);
    $bodyContactClean = btb_email_template_strip_autogenerated_detail_lines($bodyContact);

    $headingR = htmlspecialchars(btbEmailTemplateInterpolate($heading, $vars), ENT_QUOTES, 'UTF-8');
    $bodyR = htmlspecialchars(btbEmailTemplateInterpolate($bodyClean, $vars), ENT_QUOTES, 'UTF-8');
    $bodyR = nl2br($bodyR);
    $bodyAfterR = htmlspecialchars(btbEmailTemplateInterpolate($bodyAfterClean, $vars), ENT_QUOTES, 'UTF-8');
    $bodyAfterR = nl2br($bodyAfterR);
    $bodyContactR = htmlspecialchars(btbEmailTemplateInterpolate($bodyContactClean, $vars), ENT_QUOTES, 'UTF-8');
    $bodyContactR = nl2br($bodyContactR);
    $imgUrl = btbEmailTemplateSafeHref(btbEmailTemplateInterpolate($imgUrlRaw, $vars));
    if ($imgUrl !== '') {
        $imgUrl = btb_email_safe_public_url($imgUrl);
    }

    $imgBlock = '';
    if ($imgUrl !== '') {
        $imgEsc = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
        $imgBlock = '<div class="btb-email-hero-slot" style="margin:0 0 22px;text-align:center;line-height:0;">'
            . '<img src="' . $imgEsc . '" alt="" width="560" style="display:inline-block;max-width:100%;width:100%;height:auto;border:0;border-radius:10px;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" />'
            . '</div>';
    }
    $combinedPayInner = trim((string) ($vars['combined_payment_summary_html'] ?? ''));
    $suppressSummaryForCombinedPay = $combinedPayInner !== ''
        && (
            $templateKey === 'guest_payment_succeeded_guest'
            || $templateKey === 'booking_payment_succeeded_host'
            || $templateKey === 'massage_payment_succeeded_host'
        );

    $summaryHtml = ($templateKey === 'guest_bookings_digest_guest' || $templateKey === 'user_register_welcome')
        ? ''
        : btb_email_template_build_structured_summary_html($templateKey, $vars);
    if ($suppressSummaryForCombinedPay) {
        $summaryHtml = '';
    }

    $guestMessageCardHtml = '';
    if (
        function_exists('btb_email_template_build_guest_message_card_html')
        && function_exists('btb_email_template_booking_guest_message_from_vars')
        && ($templateKey === 'booking_request_host' || $templateKey === 'massage_booking_host')
    ) {
        $guestMessageCardHtml = btb_email_template_build_guest_message_card_html(
            btb_email_template_booking_guest_message_from_vars($vars)
        );
    }

    $bodyFont = 'font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;';

    $digestBlock = '';
    if ($templateKey === 'guest_bookings_digest_guest' && $digestInner !== '') {
        $digestBlock = '<div class="btb-email-digest" style="margin-top:10px;' . $bodyFont . 'font-size:15px;line-height:1.65;color:#1f2937;">'
            . $digestInner . '</div>';
    }

    $combinedPayBlock = '';
    if ($combinedPayInner !== '') {
        $combinedPayBlock = '<div class="btb-email-combined-payment" style="margin-top:18px;' . $bodyFont . 'font-size:15px;line-height:1.65;color:#1f2937;">'
            . $combinedPayInner . '</div>';
    }

    $logoBlock = btb_email_template_header_logo_html();
    $footerBandHtml = btb_email_template_mail_footer_band_html($vars, $templateKey, $cardHex);

    $contentInner = $logoBlock
        . $imgBlock
        . ($headingR !== '' ? '<h1 class="btb-email-heading" style="margin:8px 0 18px;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;font-size:19px;font-weight:700;line-height:1.35;letter-spacing:-0.02em;color:#1e293b;">' . $headingR . '</h1>' : '')
        . ($bodyR !== '' ? '<div class="btb-email-bodytext" style="' . $bodyFont . 'font-size:16px;line-height:1.65;color:#1f2937;margin:0;">' . $bodyR . '</div>' : '')
        . $digestBlock
        . $combinedPayBlock
        . $summaryHtml
        . $guestMessageCardHtml
        . ($bodyAfterR !== '' ? '<div class="btb-email-bodytext" style="margin-top:22px;' . $bodyFont . 'font-size:16px;line-height:1.65;color:#1f2937;">' . $bodyAfterR . '</div>' : '')
        . ($bodyContactR !== '' ? '<div class="btb-email-bodycontact" style="margin-top:18px;padding-top:1.55em;text-align:right;' . $bodyFont . 'font-size:15px;line-height:1.55;color:#475569;">' . $bodyContactR . '</div>' : '');

    $primaryCtaBlock = '';
    if ($ctaLabel !== '' && trim($ctaUrlRaw) !== '') {
        $ctaHrefResolved = btb_email_safe_public_url(btbEmailTemplateSafeHref(btbEmailTemplateInterpolate($ctaUrlRaw, $vars)));
        if ($ctaHrefResolved !== '') {
            $primaryCtaBlock = btb_email_template_primary_blue_cta_html(
                btbEmailTemplateInterpolate($ctaLabel, $vars),
                $ctaHrefResolved
            );
        }
    }
    if ($primaryCtaBlock === '' && btb_email_template_key_is_host($templateKey)) {
        $primaryCtaBlock = btb_email_template_primary_blue_cta_html('Open admin panel', btb_email_template_admin_panel_href($vars));
    }
    $contentInner .= $primaryCtaBlock;

    // Footer hero: DB `email_branding_settings`; empty URL → BTB_EMAIL_DEFAULT_FOOTER_IMAGE_URL. Editable under Admin → Emails → Footer banner.
    $footerDb = trim((string) ($br['footer_image_url'] ?? ''));
    $footerFallback = defined('BTB_EMAIL_DEFAULT_FOOTER_IMAGE_URL') ? trim((string) BTB_EMAIL_DEFAULT_FOOTER_IMAGE_URL) : '';
    $footerAbs = btb_email_safe_public_url($footerDb !== '' ? $footerDb : $footerFallback);
    $footerAltPlain = trim((string) ($br['footer_image_alt'] ?? ''));
    $footerRow = '';
    if ($footerAbs !== '' && !btb_email_template_key_is_host($templateKey)) {
        $heroInner = btb_email_footer_hero_overlay_block_html($footerAbs, $footerAltPlain !== '' ? $footerAltPlain : 'Back to Base');
        $footerRow = '<tr><td align="center" width="100%" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="padding:0;line-height:0;font-size:0;background:' . $card . ';text-align:center;">'
            . $heroInner
            . '</td></tr>';
    }

    $footerBridge = trim($footerBandHtml) !== ''
        ? ('<tr><td class="btb-email-card-inner" style="padding:0;background:' . $card . ';">' . $footerBandHtml . '</td></tr>')
        : '';

    $v2css = btb_email_template_v2_embedded_css($outerHex, $cardHex);

    return '<!--btb-email-shell-v2-->'
        . '<!DOCTYPE html><html lang="en" class="btb-email-html" style="color-scheme:light only;"><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="color-scheme" content="light only">'
        . '<meta name="supported-color-schemes" content="light only">'
        . '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->'
        . '<title></title>' . $v2css . '</head>'
        . '<body class="btb-email-body" bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="margin:0;padding:0;background:' . $outer . ' !important;color:#111827;color-scheme:light only;">'
        . '<table role="presentation" class="btb-email-outer" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;background:' . $outer . ';">'
        . '<tr><td class="btb-email-outer-cell" align="center" style="padding:20px 14px 28px 14px;background:' . $outer . ';">'
        . '<table role="presentation" class="btb-email-card" width="640" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;max-width:640px;width:100%;background:' . $card . ';border-radius:14px;border:1px solid #e5e7eb;overflow:hidden;">'
        . '<tr><td class="btb-email-card-inner" style="padding:26px 26px 26px 26px;background:' . $card . ';">' . $contentInner . '</td></tr>'
        . $footerBridge
        . $footerRow
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}

function btbEmailTemplateApplyAdminOverride(string $templateKey, array $vars, string $fallbackSubject, string $fallbackHtml): array
{
    global $conn;
    $vars = btb_email_template_merge_default_placeholders($vars);
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
    $guestMsg = function_exists('btb_normalize_guest_message')
        ? btb_normalize_guest_message($booking['special_requests'] ?? '')
        : trim((string) ($booking['special_requests'] ?? ''));
    $guestMsgBlock = '';
    if ($guestMsg !== '' && function_exists('btb_email_template_build_guest_message_card_html')) {
        $guestMsgBlock = btb_email_template_build_guest_message_card_html($guestMsg);
    }
    
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
                {$guestMsgBlock}
                
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
    $payUrl = '';
    if (!empty(STRIPE_SECRET_KEY) && floatval($booking['total_amount'] ?? 0) > 0 && function_exists('btb_guest_room_pay_url')) {
        $payUrl = btb_guest_room_pay_url($booking);
    }
    $payBlock = '';
    if ($payUrl !== '') {
        $payEsc = htmlspecialchars($payUrl, ENT_QUOTES, 'UTF-8');
        $payBlock = "<p style='text-align:center;margin:22px 0;'><a href='{$payEsc}' style='display:inline-block;padding:14px 22px;background:#1e3a5f;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;'>Pay now</a></p>"
            . "<p style='font-size:14px;color:#4a5568;'>If the button does not work, copy this link into your browser:<br><span style='word-break:break-all;'>{$payEsc}</span></p>";
    }
    
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
                {$payBlock}
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
        return sendEmail($email, $ov['subject'], $ov['html'], '', [
            'template_key' => 'massage_booking_guest',
            'context' => ['booking' => $mb],
        ]);
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
        if (!btb_host_booking_request_emails_enabled()) {
            $skipReason = !btb_email_host_notifications_enabled()
                ? 'BTB_EMAIL_HOST_NOTIFICATIONS_ENABLED is off'
                : 'BTB_HOST_BOOKING_REQUEST_EMAILS_ENABLED is off';
            logActivity("Host wellness booking request email skipped ({$skipReason})", 'INFO');

            return [
                'success' => true,
                'message' => 'Host booking request emails temporarily disabled',
            ];
        }

        $hostEmail = btb_host_notification_email();
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
        $guestMsg = function_exists('btb_normalize_guest_message')
            ? btb_normalize_guest_message($mb['guest_message'] ?? '')
            : trim((string) ($mb['guest_message'] ?? ''));

        $subject = 'New wellness booking (massage / sauna) — Back to Base';
        $ov = btbEmailTemplateApplyAdminOverride('massage_booking_host', [
            'booking' => $mb,
            'booking_guest_message' => $guestMsg,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], $subject, $htmlContent);
        return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'massage_booking_host']);
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
    sendEmail($guest, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'room_booking_updated_guest']);
    $host = btb_host_notification_email();
    if (!empty($host) && filter_var($host, FILTER_VALIDATE_EMAIL)) {
        $hostOv = btbEmailTemplateApplyAdminOverride('room_booking_updated_host', [
            'before' => $before,
            'after' => $after,
            'booking' => $after,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'booking_url' => BOOKING_CONFIRMATION_URL . '?code=' . urlencode((string) ($after['confirmation_code'] ?? '')),
            'site_url' => SITE_URL,
        ], 'Guest updated a room booking — Back to Base', $html);
        btb_send_email_to_host_inboxes($hostOv['subject'], $hostOv['html'], '', ['template_key' => 'room_booking_updated_host']);
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
    sendEmail($guest, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'massage_booking_updated_guest']);
    $host = btb_host_notification_email();
    if (!empty($host) && filter_var($host, FILTER_VALIDATE_EMAIL)) {
        $hostOv = btbEmailTemplateApplyAdminOverride('massage_booking_updated_host', [
            'before' => $before,
            'after' => $after,
            'booking' => $after,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], 'Guest updated a wellness booking — Back to Base', $html);
        btb_send_email_to_host_inboxes($hostOv['subject'], $hostOv['html'], '', ['template_key' => 'massage_booking_updated_host']);
    }
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

/** Attribution line for staff reply chat emails (override via BTB_CHAT_STAFF_FROM_LABEL). */
function btb_chat_staff_from_label(): string
{
    if (defined('BTB_CHAT_STAFF_FROM_LABEL')) {
        $s = trim((string) BTB_CHAT_STAFF_FROM_LABEL);
        if ($s !== '') {
            return $s;
        }
    }

    return 'Rob at Back to Base';
}

/**
 * Last N chat lines for staff-reply email (oldest → newest among the tail).
 *
 * @return list<array{sender:string,body:string}>
 */
function btb_host_chat_load_recent_messages_for_email(?mysqli $conn, int $threadId, int $limit = 4): array
{
    if (!$conn || $threadId <= 0 || $limit <= 0) {
        return [];
    }
    $lim = min(50, max(1, $limit));
    $stmt = @$conn->prepare(
        'SELECT `sender`, `body` FROM `host_chat_messages` WHERE `thread_id` = ? ORDER BY `id` DESC LIMIT ' . (int) $lim
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $threadId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $buf = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }
            $snd = (string) ($row['sender'] ?? '');
            if ($snd !== 'guest' && $snd !== 'staff') {
                $snd = 'guest';
            }
            $buf[] = ['sender' => $snd, 'body' => (string) ($row['body'] ?? '')];
        }
    }
    $stmt->close();

    return array_reverse($buf);
}

/**
 * Variables for My Account chat emails (guest message → host; staff reply → guest).
 * Chats have no thread subject in copy — use heading_line + message_preview only.
 *
 * @param 'host'|'guest' $audience
 * @param list<array{sender:string,body:string}> $recentMessages
 * @return array<string,mixed>
 */
function btb_host_chat_email_vars(string $guestName, string $guestEmail, string $bodySnippet, string $audience, array $recentMessages = []): array
{
    $gn = trim($guestName);
    if ($audience === 'guest') {
        $headingLine = 'You have a new message from ' . btb_chat_staff_from_label() . '.';
        $staffFrom = btb_chat_staff_from_label();
    } else {
        $headingLine = $gn !== '' ? ('You have a new message from ' . $guestName . '.') : 'You have a new message from a guest.';
        $staffFrom = '';
    }

    $chat = [
        'message_preview' => $bodySnippet,
        'heading_line' => $headingLine,
        'staff_from' => $staffFrom,
    ];
    if ($audience === 'guest' && $recentMessages !== []) {
        $chat['recent_messages'] = array_values($recentMessages);
    }

    $vars = [
        'chat' => $chat,
        'user' => ['name' => $guestName, 'email' => $guestEmail],
        'site_url' => defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '',
    ];
    if (defined('GUEST_DASHBOARD_URL')) {
        $vars['dashboard_url'] = (string) GUEST_DASHBOARD_URL;
    }
    if (defined('ADMIN_BOOKINGS_URL')) {
        $vars['admin_url'] = (string) ADMIN_BOOKINGS_URL;
    }

    return $vars;
}

/**
 * Queue host notification when a guest sends a chat message (runs after HTTP response).
 */
function btb_queue_host_chat_guest_message_email(string $guestName, string $guestEmail, string $bodySnippet): void
{
    if (!function_exists('btb_send_email_to_host_inboxes') || !function_exists('btb_host_notification_emails')) {
        return;
    }
    if (btb_host_notification_emails() === []) {
        return;
    }
    $snippet = mb_substr($bodySnippet, 0, 2000);
    $vars = btb_host_chat_email_vars($guestName, $guestEmail, $snippet, 'host');
    register_shutdown_function(function () use ($vars, $snippet) {
        try {
            if (!function_exists('btbEmailTemplateApplyAdminOverride')) {
                return;
            }
            $fallbackSubject = 'New chat message';
            $prev = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
            $head = htmlspecialchars((string) ($vars['chat']['heading_line'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adm = function_exists('btb_email_template_admin_panel_href') ? btb_email_template_admin_panel_href($vars) : '';
            $admEsc = htmlspecialchars($adm, ENT_QUOTES, 'UTF-8');
            $adminBtn = $adm !== ''
                ? '<p style="margin:28px 0 14px;text-align:center;"><a href="' . $admEsc . '" style="display:inline-block;padding:14px 28px;background-color:#2563eb;color:#ffffff!important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;border:2px solid #1d4ed8;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">Open admin panel</a></p>'
                : '';
            $fallbackHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;">'
                . '<p><strong>' . $head . '</strong></p>'
                . '<p><strong>From guest</strong></p><pre style="white-space:pre-wrap;font-family:inherit;">' . $prev . '</pre>'
                . $adminBtn
                . '</body></html>';
            $ov = btbEmailTemplateApplyAdminOverride('host_chat_guest_message_host', $vars, $fallbackSubject, $fallbackHtml);
            btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'host_chat_guest_message_host', 'context' => $vars]);
        } catch (Throwable $e) {
            error_log('host_chat_guest_message_host: ' . $e->getMessage());
        }
    });
}

/**
 * Queue guest notification when staff replies in chat (runs after HTTP response).
 *
 * @param array<string,mixed> $guestUser users row fragment: name, email
 */
function btb_queue_guest_chat_staff_reply_email(array $guestUser, string $replyBody, int $threadId = 0): void
{
    if (!function_exists('sendEmail')) {
        return;
    }
    $email = trim((string) ($guestUser['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $guestName = (string) ($guestUser['name'] ?? '');
    $snippet = mb_substr($replyBody, 0, 2000);
    register_shutdown_function(function () use ($email, $guestName, $snippet, $threadId) {
        try {
            if (!function_exists('btbEmailTemplateApplyAdminOverride')) {
                return;
            }
            global $conn;
            $recent = [];
            if ($threadId > 0 && isset($conn) && $conn instanceof mysqli) {
                $recent = btb_host_chat_load_recent_messages_for_email($conn, $threadId, 4);
            }
            $vars = btb_host_chat_email_vars($guestName, $email, $snippet, 'guest', $recent);
            $fallbackSubject = 'New message — Back to Base';
            $head = htmlspecialchars((string) ($vars['chat']['heading_line'] ?? ''), ENT_QUOTES, 'UTF-8');
            $msgPage = defined('BTB_MESSAGES_PAGE_URL') ? (string) BTB_MESSAGES_PAGE_URL : ($vars['site_url'] !== '' ? rtrim((string) $vars['site_url'], '/') . '/messages.html' : '');
            $msgPageEsc = htmlspecialchars(btb_email_safe_public_url($msgPage !== '' ? $msgPage : '#'), ENT_QUOTES, 'UTF-8');
            $fallbackBody = '';
            if ($recent !== []) {
                $staffLbl = htmlspecialchars(function_exists('btb_chat_staff_from_label') ? btb_chat_staff_from_label() : 'Back to Base', ENT_QUOTES, 'UTF-8');
                foreach ($recent as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $snd = (string) ($m['sender'] ?? '');
                    $raw = (string) ($m['body'] ?? '');
                    $lab = $snd === 'staff' ? $staffLbl : 'You';
                    $labEsc = htmlspecialchars($lab, ENT_QUOTES, 'UTF-8');
                    $fallbackBody .= '<p style="margin:12px 0 4px;"><strong>' . $labEsc . '</strong></p>'
                        . '<pre style="white-space:pre-wrap;font-family:inherit;margin:0 0 14px;">' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '</pre>';
                }
            }
            if ($fallbackBody === '') {
                $fallbackBody = '<p><strong>Latest reply</strong></p><pre style="white-space:pre-wrap;font-family:inherit;">'
                    . htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . '</pre>';
            }
            $fallbackHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;">'
                . '<p><strong>' . $head . '</strong></p>'
                . $fallbackBody
                . '<p style="margin:28px 0 14px;text-align:center;">'
                . '<a href="' . $msgPageEsc . '" style="display:inline-block;padding:14px 28px;background-color:#2563eb;color:#ffffff!important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;border:2px solid #1d4ed8;font-family:Inter,system-ui,-apple-system,\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">Chat with Rob</a>'
                . '</p></body></html>';
            $ov = btbEmailTemplateApplyAdminOverride('guest_chat_staff_reply_guest', $vars, $fallbackSubject, $fallbackHtml);
            sendEmail($email, $ov['subject'], $ov['html'], '', ['template_key' => 'guest_chat_staff_reply_guest', 'context' => $vars]);
        } catch (Throwable $e) {
            error_log('guest_chat_staff_reply_guest: ' . $e->getMessage());
        }
    });
}

/**
 * Resolve guest email for combined-checkout rows (expects one email per PaymentIntent).
 *
 * @param array<int,array<string,mixed>> $roomBookings
 * @param array<int,array<string,mixed>> $massageBookings
 */
function btb_combined_stripe_payment_guest_email(array $roomBookings, array $massageBookings): string {
    $seen = [];
    foreach ($roomBookings as $b) {
        $e = trim((string) ($b['email'] ?? ''));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $seen[strtolower($e)] = $e;
        }
    }
    foreach ($massageBookings as $mb) {
        $e = trim((string) ($mb['email'] ?? ''));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $seen[strtolower($e)] = $e;
        }
    }
    if (count($seen) > 1) {
        error_log('Combined Stripe payment: multiple guest emails on one PaymentIntent');
    }

    return $seen !== [] ? reset($seen) : '';
}

/**
 * HTML fragment: PaymentIntent id, Stripe total, bullet list of room + wellness lines.
 *
 * @param array<int,array<string,mixed>> $roomBookings
 * @param array<int,array<string,mixed>> $massageBookings
 */
function btb_build_combined_payment_summary_html(array $roomBookings, array $massageBookings, string $paymentIntentId, int $amountCents, string $currency): string {
    $esc = static function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    };
    $cur = strtoupper(trim($currency) !== '' ? trim($currency) : 'CAD');
    $out = '<p style="margin:0 0 10px;"><strong>Payment reference:</strong> ' . $esc($paymentIntentId) . '</p>';
    if ($amountCents > 0) {
        $out .= '<p style="margin:0 0 14px;"><strong>Total charged:</strong> ' . $esc(number_format($amountCents / 100, 2) . ' ' . $cur) . '</p>';
    }
    $items = [];
    foreach ($roomBookings as $b) {
        $guest = trim((string) ($b['guest_name'] ?? ''));
        $room = trim((string) ($b['room_name'] ?? ''));
        $in = trim((string) ($b['checkin_date'] ?? ''));
        $outd = trim((string) ($b['checkout_date'] ?? ''));
        $code = trim((string) ($b['confirmation_code'] ?? ''));
        $amt = trim((string) ($b['total_amount'] ?? ''));
        $bits = ['<strong>Room stay</strong>'];
        if ($room !== '') {
            $bits[] = $esc($room);
        }
        $stay = trim($in . (($in !== '' || $outd !== '') ? ' – ' : '') . $outd);
        if ($stay !== '') {
            $bits[] = $esc($stay);
        }
        if ($code !== '') {
            $bits[] = 'ref. ' . $esc($code);
        }
        if ($amt !== '') {
            $bits[] = $esc($amt . ' ' . $cur);
        }
        if ($guest !== '') {
            $bits[] = '(' . $esc($guest) . ')';
        }
        $line = implode(' · ', array_filter($bits, static function ($x) {
            return $x !== '';
        }));
        if ($line !== '') {
            $items[] = '<li style="margin:0 0 8px;">' . $line . '</li>';
        }
    }
    foreach ($massageBookings as $mb) {
        $guest = trim((string) ($mb['guest_name'] ?? ''));
        $svc = trim((string) ($mb['massage_type'] ?? ''));
        $dur = (int) ($mb['duration'] ?? 0);
        $svcLine = $svc !== '' ? ($dur > 0 ? $svc . ' (' . $dur . ' min)' : $svc) : '';
        $d = trim((string) ($mb['massage_date'] ?? ''));
        $t = trim((string) ($mb['massage_time'] ?? ''));
        $code = trim((string) ($mb['confirmation_code'] ?? ''));
        $amt = trim((string) ($mb['total_amount'] ?? ''));
        $bits = ['<strong>Wellness</strong>'];
        if ($svcLine !== '') {
            $bits[] = $esc($svcLine);
        }
        $dt = trim($d . (($d !== '' && $t !== '') ? ' ' : '') . $t);
        if ($dt !== '') {
            $bits[] = $esc($dt);
        }
        if ($code !== '') {
            $bits[] = 'ref. ' . $esc($code);
        }
        if ($amt !== '') {
            $bits[] = $esc($amt . ' ' . $cur);
        }
        if ($guest !== '') {
            $bits[] = '(' . $esc($guest) . ')';
        }
        $line = implode(' · ', array_filter($bits, static function ($x) {
            return $x !== '';
        }));
        if ($line !== '') {
            $items[] = '<li style="margin:0 0 8px;">' . $line . '</li>';
        }
    }
    if ($items !== []) {
        $out .= '<p style="margin:16px 0 8px;font-weight:600;color:#1e293b;">Included in this payment:</p>'
            . '<ul style="margin:0;padding-left:20px;">' . implode('', $items) . '</ul>';
    }

    return $out;
}

/**
 * Send guest + host payment-success emails once per successful charge (idempotent via processSuccessfulPayment flag).
 *
 * @param array<string,mixed> $result Return value from processSuccessfulPayment()
 * @param array<string,mixed> $paymentIntent Stripe PaymentIntent object (amount, currency) for combined summary
 */
function btb_dispatch_payment_success_emails(array $result, string $paymentIntentId, array $paymentIntent = []): void
{
    if (empty($result['success']) || empty($result['send_success_emails'])) {
        return;
    }
    if (empty(MAILGUN_API_KEY)) {
        return;
    }

    try {
        $kind = (string) ($result['kind'] ?? 'room');
        if ($kind === 'combined' && function_exists('sendCombinedStripePaymentSucceededEmails')) {
            sendCombinedStripePaymentSucceededEmails(
                $result['room_bookings'] ?? [],
                $result['massage_bookings'] ?? [],
                $paymentIntentId,
                $paymentIntent
            );

            return;
        }
        if ($kind === 'massage' && !empty($result['massage_booking']) && function_exists('sendMassagePaymentSucceededToGuestAndHost')) {
            sendMassagePaymentSucceededToGuestAndHost($result['massage_booking'], $paymentIntentId);

            return;
        }
        if (!empty($result['booking']) && function_exists('sendRoomPaymentSucceededToGuestAndHost')) {
            sendRoomPaymentSucceededToGuestAndHost($result['booking'], $paymentIntentId);
        }
    } catch (Throwable $e) {
        error_log('btb_dispatch_payment_success_emails: ' . $e->getMessage());
        if (function_exists('logActivity')) {
            logActivity('Payment success emails failed: ' . $e->getMessage(), 'WARNING');
        }
    }
}

/**
 * One guest + one host email after a combined Stripe PaymentIntent (room + wellness in one charge).
 *
 * @param array<int,array<string,mixed>> $roomBookings
 * @param array<int,array<string,mixed>> $massageBookings
 * @param array<string,mixed> $paymentIntent Stripe PaymentIntent object (amount, currency)
 */
function sendCombinedStripePaymentSucceededEmails(array $roomBookings, array $massageBookings, string $paymentIntentId, array $paymentIntent): void {
    $amountCents = (int) ($paymentIntent['amount'] ?? 0);
    $currency = (string) ($paymentIntent['currency'] ?? 'cad');
    $summaryInner = btb_build_combined_payment_summary_html($roomBookings, $massageBookings, $paymentIntentId, $amountCents, $currency);

    $guestEmail = btb_combined_stripe_payment_guest_email($roomBookings, $massageBookings);
    $rep = [];
    if ($roomBookings !== []) {
        $rep = $roomBookings[0];
    } elseif ($massageBookings !== []) {
        $rep = $massageBookings[0];
    }

    if (filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        $guestName = htmlspecialchars((string) ($rep['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
            . "<h2 style='color:#2d6a4f;'>Payment received</h2><p>Dear {$guestName}, your payment was received successfully.</p>"
            . $summaryInner
            . '</body></html>';
        $guestOv = btbEmailTemplateApplyAdminOverride('guest_payment_succeeded_guest', [
            'booking' => $rep,
            'payment_intent_id' => $paymentIntentId,
            'site_url' => SITE_URL,
            'combined_payment_summary_html' => $summaryInner,
        ], 'Payment received — Back to Base', $html);
        sendEmail($guestEmail, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'guest_payment_succeeded_guest', 'context' => ['booking' => $rep, 'payment_intent_id' => $paymentIntentId, 'combined' => true]]);
    }

    $hostEmail = btb_host_notification_email();
    if (!filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $hostKey = $roomBookings !== [] ? 'booking_payment_succeeded_host' : 'massage_payment_succeeded_host';
    $hostSubject = $hostKey === 'booking_payment_succeeded_host'
        ? 'Booking payment received — Back to Base'
        : 'Wellness booking payment received — Back to Base';
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#1e3a5f;'>Payment received</h2>"
        . '<p>A guest completed an online payment.</p>'
        . $summaryInner
        . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
    $hostOv = btbEmailTemplateApplyAdminOverride($hostKey, [
        'booking' => $rep,
        'payment_intent_id' => $paymentIntentId,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
        'combined_payment_summary_html' => $summaryInner,
    ], $hostSubject, $html);
    btb_send_email_to_host_inboxes($hostOv['subject'], $hostOv['html'], '', ['template_key' => $hostKey, 'context' => ['booking' => $rep, 'payment_intent_id' => $paymentIntentId, 'combined' => true]]);
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
        $guestOv = btbEmailTemplateApplyAdminOverride('guest_payment_succeeded_guest', [
            'booking' => $booking,
            'payment_intent_id' => $paymentIntentId,
            'site_url' => SITE_URL,
        ], 'Payment received — Back to Base', $html);
        sendEmail($guestEmail, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'guest_payment_succeeded_guest', 'context' => ['booking' => $booking, 'payment_intent_id' => $paymentIntentId]]);
    }
    $hostEmail = btb_host_notification_email();
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
        btb_send_email_to_host_inboxes($hostOv['subject'], $hostOv['html'], '', ['template_key' => 'booking_payment_succeeded_host', 'context' => ['booking' => $booking, 'payment_intent_id' => $paymentIntentId]]);
    }
}

/**
 * Wellness booking: payment succeeded (Stripe) — guest and host.
 *
 * @param array $mb massage_bookings row
 */
function sendMassagePaymentSucceededToGuestAndHost(array $mb, string $paymentIntentId = '') {
    $guestEmail = trim((string) ($mb['email'] ?? ''));
    if (filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        $name = htmlspecialchars((string) ($mb['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars((string) ($mb['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars((string) ($mb['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars((string) ($mb['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string) ($mb['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars((string) ($mb['currency'] ?? 'CAD'), ENT_QUOTES, 'UTF-8');
        $pi = htmlspecialchars($paymentIntentId, ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string) ($mb['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
            . "<h2 style='color:#2d6a4f;'>Payment received</h2><p>Dear {$name}, your payment for your wellness booking was received.</p>"
            . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Service:</strong> {$type}</li>"
            . "<li><strong>Date:</strong> {$date}</li><li><strong>Time:</strong> {$time}</li>"
            . "<li><strong>Amount:</strong> {$amount} {$currency}</li><li><strong>Payment reference:</strong> {$pi}</li></ul></body></html>";
        $guestOv = btbEmailTemplateApplyAdminOverride('guest_payment_succeeded_guest', [
            'booking' => $mb,
            'payment_intent_id' => $paymentIntentId,
            'site_url' => SITE_URL,
        ], 'Payment received — Back to Base', $html);
        sendEmail($guestEmail, $guestOv['subject'], $guestOv['html'], '', ['template_key' => 'guest_payment_succeeded_guest', 'context' => ['booking' => $mb, 'payment_intent_id' => $paymentIntentId]]);
    }
    $hostEmail = btb_host_notification_email();
    if (filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        $bid = (int) ($mb['id'] ?? 0);
        $guest = htmlspecialchars((string) ($mb['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string) ($mb['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8');
        $currency = htmlspecialchars((string) ($mb['currency'] ?? 'CAD'), ENT_QUOTES, 'UTF-8');
        $pi = htmlspecialchars($paymentIntentId, ENT_QUOTES, 'UTF-8');
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
            . "<h2 style='color:#1e3a5f;'>Wellness payment received</h2>"
            . "<p>Wellness booking #{$bid} for {$guest} has been paid.</p>"
            . "<ul><li><strong>Amount:</strong> {$amount} {$currency}</li><li><strong>Payment reference:</strong> {$pi}</li></ul>"
            . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
        $hostOv = btbEmailTemplateApplyAdminOverride('massage_payment_succeeded_host', [
            'booking' => $mb,
            'payment_intent_id' => $paymentIntentId,
            'admin_url' => ADMIN_BOOKINGS_URL,
            'site_url' => SITE_URL,
        ], 'Wellness booking payment received — Back to Base', $html);
        btb_send_email_to_host_inboxes($hostOv['subject'], $hostOv['html'], '', ['template_key' => 'massage_payment_succeeded_host', 'context' => ['booking' => $mb, 'payment_intent_id' => $paymentIntentId]]);
    }
}

/**
 * Host copy when a room booking is approved in admin (guest gets guest_bookings_digest_guest when nothing is pending).
 */
function sendRoomBookingConfirmedToHost(array $booking) {
    $hostEmail = btb_host_notification_email();
    if (!filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Host email not configured'];
    }
    $guest = htmlspecialchars((string) ($booking['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $room = htmlspecialchars((string) ($booking['room_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars((string) ($booking['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $in = htmlspecialchars((string) ($booking['checkin_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $out = htmlspecialchars((string) ($booking['checkout_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#1e3a5f;'>Room booking approved</h2>"
        . "<p>You approved a room booking for <strong>{$guest}</strong>.</p>"
        . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Room:</strong> {$room}</li>"
        . "<li><strong>Check-in:</strong> {$in}</li><li><strong>Check-out:</strong> {$out}</li></ul>"
        . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('booking_confirmed_host', [
        'booking' => $booking,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
    ], 'Room booking approved (guest notified) — Back to Base', $html);
    return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'booking_confirmed_host', 'context' => ['booking' => $booking]]);
}

/**
 * Host copy when a room booking is cancelled in admin.
 */
function sendRoomBookingCancelledToHost(array $booking, string $reason = '') {
    $hostEmail = btb_host_notification_email();
    if (!filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Host email not configured'];
    }
    $guest = htmlspecialchars((string) ($booking['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $room = htmlspecialchars((string) ($booking['room_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reasonHtml = $reason !== '' ? '<p><strong>Reason:</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#8b1e3f;'>Room booking cancelled</h2>"
        . "<p>A room booking for <strong>{$guest}</strong> was cancelled or declined.</p>"
        . "<ul><li><strong>Room:</strong> {$room}</li></ul>"
        . $reasonHtml
        . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('booking_cancelled_host', [
        'booking' => $booking,
        'reason' => $reason,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
    ], 'Room booking cancelled — Back to Base', $html);
    return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'booking_cancelled_host', 'context' => ['booking' => $booking, 'reason' => $reason]]);
}

/**
 * Host copy when wellness booking is confirmed in admin.
 */
function sendMassageBookingConfirmedToHost(array $mb) {
    $hostEmail = btb_host_notification_email();
    if (!filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Host email not configured'];
    }
    $guest = htmlspecialchars((string) ($mb['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars((string) ($mb['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($mb['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string) ($mb['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dur = (int) ($mb['duration'] ?? 0);
    $code = htmlspecialchars((string) ($mb['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bid = (int) ($mb['id'] ?? 0);
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#1e3a5f;'>Wellness booking confirmed</h2>"
        . "<p>You confirmed wellness booking #{$bid} for <strong>{$guest}</strong>.</p>"
        . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Service:</strong> {$type} ({$dur} min)</li>"
        . "<li><strong>Date:</strong> {$date}</li><li><strong>Time:</strong> {$time}</li></ul>"
        . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('massage_booking_confirmed_host', [
        'booking' => $mb,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
    ], 'Wellness booking confirmed (guest notified) — Back to Base', $html);
    return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'massage_booking_confirmed_host', 'context' => ['booking' => $mb]]);
}

/**
 * Host copy when wellness booking is cancelled in admin.
 */
function sendMassageBookingCancelledToHost(array $mb, string $reason = '') {
    $hostEmail = btb_host_notification_email();
    if (!filter_var($hostEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Host email not configured'];
    }
    $guest = htmlspecialchars((string) ($mb['guest_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars((string) ($mb['massage_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string) ($mb['massage_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string) ($mb['massage_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars((string) ($mb['confirmation_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bid = (int) ($mb['id'] ?? 0);
    $reasonHtml = $reason !== '' ? '<p><strong>Reason:</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>' : '';
    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<h2 style='color:#8b1e3f;'>Wellness booking cancelled</h2>"
        . "<p>Wellness booking #{$bid} for <strong>{$guest}</strong> was cancelled.</p>"
        . "<ul><li><strong>Reference:</strong> {$code}</li><li><strong>Service:</strong> {$type}</li>"
        . "<li><strong>Date:</strong> {$date}</li><li><strong>Time:</strong> {$time}</li></ul>"
        . $reasonHtml
        . "<p><a href='" . htmlspecialchars(ADMIN_BOOKINGS_URL, ENT_QUOTES, 'UTF-8') . "'>Open admin bookings</a></p></body></html>";
    $ov = btbEmailTemplateApplyAdminOverride('massage_booking_cancelled_host', [
        'booking' => $mb,
        'reason' => $reason,
        'admin_url' => ADMIN_BOOKINGS_URL,
        'site_url' => SITE_URL,
    ], 'Wellness booking cancelled (guest notified) — Back to Base', $html);
    return btb_send_email_to_host_inboxes($ov['subject'], $ov['html'], '', ['template_key' => 'massage_booking_cancelled_host', 'context' => ['booking' => $mb, 'reason' => $reason]]);
}

?>

