<?php
// Common PHP utilities for Back to Base

// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';
require_once __DIR__ . '/seo_helper.php';

// Common response functions
function sendResponse($success, $data = null, $error = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

function sendSuccess($data = null) {
    sendResponse(true, $data);
}

function sendError($error, $data = null, $httpCode = null) {
    if ($httpCode !== null && is_numeric($httpCode)) {
        http_response_code((int) $httpCode);
    }
    sendResponse(false, $data, $error);
}

/**
 * All host/staff notification inboxes (primary + optional override). Lowercase, deduplicated.
 *
 * @return list<string>
 */
function btb_host_notification_emails(): array
{
    $out = [];
    $seen = [];
    $add = static function (string $raw) use (&$out, &$seen): void {
        $e = strtolower(trim($raw));
        if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || isset($seen[$e])) {
            return;
        }
        $seen[$e] = true;
        $out[] = $e;
    };
    if (defined('MAILGUN_HOST_EMAIL')) {
        $add((string) MAILGUN_HOST_EMAIL);
    }
    if (defined('BTB_HOST_EMAIL_OVERRIDE')) {
        $add((string) BTB_HOST_EMAIL_OVERRIDE);
    }

    return $out;
}

/** Primary host inbox (first in {@see btb_host_notification_emails()}). */
function btb_host_notification_email(): string
{
    $list = btb_host_notification_emails();

    return $list[0] ?? '';
}

/**
 * Fixed-window rate limit (file + flock). Fails open if temp dir is not writable.
 *
 * @param string $bucket Stable key, e.g. "guest_chat_send:42"
 * @param int $maxEvents Max allowed events per window (inclusive)
 * @param int $windowSeconds Window length in seconds
 */
function btb_rate_limit_enforce(string $bucket, int $maxEvents, int $windowSeconds): void {
    if ($maxEvents < 1 || $windowSeconds < 1) {
        return;
    }
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'btb_ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return;
    }
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bucket);
    if (strlen($safe) > 180) {
        $safe = 'h_' . hash('sha256', $bucket);
    }
    $path = $dir . DIRECTORY_SEPARATOR . $safe . '.json';
    $now = time();
    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }
    $raw = stream_get_contents($fp);
    $st = $raw ? json_decode($raw, true) : null;
    if (!is_array($st) || empty($st['start']) || ($now - (int) $st['start']) >= $windowSeconds) {
        $st = ['start' => $now, 'count' => 0];
    }
    if ((int) $st['count'] >= $maxEvents) {
        $retry = max(1, $windowSeconds - ($now - (int) $st['start']));
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        header('Retry-After: ' . $retry);
        sendError('Too many requests. Please wait and try again.');
    }
    $st['count'] = (int) $st['count'] + 1;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($st));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/** Remove NULs and trim for chat / text fields */
function btb_sanitize_chat_body(string $body): string {
    return str_replace("\0", '', $body);
}

/** Shared label for optional guest → host message on booking forms (rooms + wellness). */
function btb_guest_message_field_label(): string
{
    return 'Message to host (optional)';
}

/**
 * Optional guest message from booking forms: trim, sanitize, cap length; empty string if blank.
 */
function btb_normalize_guest_message($raw): string
{
    $s = trim((string) $raw);
    if ($s === '') {
        return '';
    }
    if (function_exists('sanitizeInput')) {
        $s = sanitizeInput($s);
    }
    $s = btb_sanitize_chat_body($s);
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if (mb_strlen($s) > 2000) {
        $s = mb_substr($s, 0, 2000);
    }

    return $s;
}

/**
 * Extend host_chat_threads for guests without accounts (guest_email + nullable user_id).
 */
function btb_host_chat_ensure_guest_email_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (function_exists('btb_host_chat_ensure_tables')) {
        btb_host_chat_ensure_tables($conn);
    }

    $chk = @$conn->query("SHOW COLUMNS FROM `host_chat_threads` LIKE 'guest_email'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }

    @$conn->query(
        'ALTER TABLE `host_chat_threads`
         ADD COLUMN `guest_email` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`,
         ADD KEY `idx_guest_email` (`guest_email`)'
    );
}

/**
 * Ensure massage_bookings.guest_message exists (separate from legacy notes / with_room).
 */
function btb_massage_bookings_ensure_guest_message_column(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $chk = @$conn->query("SHOW TABLES LIKE 'massage_bookings'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }
    $col = @$conn->query("SHOW COLUMNS FROM `massage_bookings` LIKE 'guest_message'");
    if ($col && $col->num_rows > 0) {
        return;
    }
    @$conn->query(
        'ALTER TABLE `massage_bookings`
         ADD COLUMN `guest_message` TEXT NULL COMMENT \'Optional message to host at booking time\' AFTER `notes`'
    );
}

/**
 * Attach email-only chat threads when a guest registers or logs in.
 */
function btb_host_chat_link_threads_to_user(mysqli $conn, int $userId, string $email): void
{
    if ($userId < 1) {
        return;
    }
    if (!function_exists('btb_guest_email_normalize')) {
        return;
    }
    btb_host_chat_ensure_guest_email_schema($conn);
    $norm = btb_guest_email_normalize($email);
    if ($norm === '') {
        return;
    }
    executeQuery(
        $conn,
        'UPDATE `host_chat_threads` SET `user_id` = ? WHERE `user_id` = 0 AND LOWER(TRIM(`guest_email`)) = ?',
        [$userId, $norm]
    );
}

/**
 * Save guest booking message into host chat (no separate chat notification email).
 *
 * @return int|null thread id
 */
function btb_host_chat_save_booking_guest_message(
    mysqli $conn,
    string $email,
    string $guestName,
    string $body,
    string $subject = ''
): ?int {
    $body = btb_normalize_guest_message($body);
    if ($body === '') {
        return null;
    }

    btb_host_chat_ensure_guest_email_schema($conn);

    $emailNorm = function_exists('btb_guest_email_normalize')
        ? btb_guest_email_normalize($email)
        : strtolower(trim($email));
    if ($emailNorm === '') {
        return null;
    }

    $userRow = fetchOne($conn, 'SELECT id, name, email FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1', [$emailNorm]);
    $userId = $userRow ? (int) $userRow['id'] : 0;

    $thread = null;
    if ($userId > 0) {
        $thread = fetchOne(
            $conn,
            'SELECT id FROM host_chat_threads WHERE user_id = ? ORDER BY last_message_at DESC, id DESC LIMIT 1',
            [$userId]
        );
    }
    if (!$thread) {
        $thread = fetchOne(
            $conn,
            'SELECT id FROM host_chat_threads WHERE user_id = 0 AND LOWER(TRIM(guest_email)) = ? ORDER BY last_message_at DESC, id DESC LIMIT 1',
            [$emailNorm]
        );
    }

    $now = date('Y-m-d H:i:s');

    if ($thread) {
        $tid = (int) $thread['id'];
        $mid = insertRecord($conn, 'host_chat_messages', [
            'thread_id' => $tid,
            'sender' => 'guest',
            'body' => $body,
        ]);
        if (!$mid) {
            return null;
        }
        updateRecord($conn, 'host_chat_threads', [
            'last_message_at' => $now,
            'staff_unread' => 1,
            'guest_unread' => 0,
        ], 'id = ?', [$tid]);

        return $tid;
    }

    $subject = trim($subject);
    if ($subject === '') {
        $oneLine = preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\t"], ' ', $body));
        $oneLine = trim((string) $oneLine);
        $subject = $oneLine !== '' ? mb_substr($oneLine, 0, 120) : 'Booking message';
    }
    if (mb_strlen($subject) > 500) {
        $subject = mb_substr($subject, 0, 500);
    }

    $tid = insertRecord($conn, 'host_chat_threads', [
        'user_id' => $userId > 0 ? $userId : 0,
        'guest_email' => $emailNorm,
        'subject' => $subject,
        'last_message_at' => $now,
        'staff_unread' => 1,
        'guest_unread' => 0,
    ]);
    if (!$tid) {
        return null;
    }

    $mid = insertRecord($conn, 'host_chat_messages', [
        'thread_id' => (int) $tid,
        'sender' => 'guest',
        'body' => $body,
    ]);
    if (!$mid) {
        return null;
    }

    return (int) $tid;
}

/**
 * Best-effort client IP: first valid entry in X-Forwarded-For, else REMOTE_ADDR.
 */
function btb_client_ip_best_effort(): string {
    $xff = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim((string) $_SERVER['HTTP_X_FORWARDED_FOR']) : '';
    if ($xff !== '') {
        foreach (array_map('trim', explode(',', $xff)) as $part) {
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }
    }
    $ra = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return filter_var($ra, FILTER_VALIDATE_IP) ? $ra : '';
}

/**
 * City (and region when useful) from a public IP via ip-api.com HTTP API (free tier ~45 req/min).
 * Set BTB_DISABLE_IP_GEO to true in config to skip outbound lookups. Empty on failure or private IPs.
 */
function btb_geo_city_from_ip(string $ip): string {
    if (defined('BTB_DISABLE_IP_GEO') && BTB_DISABLE_IP_GEO) {
        return '';
    }
    $ip = trim($ip);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return '';
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,message,city,regionName,countryCode';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 2.5,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return '';
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return '';
    }
    $city = trim((string) ($data['city'] ?? ''));
    $region = trim((string) ($data['regionName'] ?? ''));
    $cc = trim((string) ($data['countryCode'] ?? ''));
    if ($city !== '') {
        if ($region !== '' && strcasecmp($region, $city) !== 0) {
            return $city . ', ' . $region;
        }

        return $city;
    }
    if ($region !== '') {
        return $cc !== '' ? ($region . ', ' . $cc) : $region;
    }

    return '';
}

// Database utilities
function executeQuery($conn, $sql, $params = []) {
    try {
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        return $stmt;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

function fetchAll($conn, $sql, $params = []) {
    try {
        $stmt = executeQuery($conn, $sql, $params);
        if (!$stmt) {
            return false;
        }
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    } catch (Exception $e) {
        error_log("Fetch all error: " . $e->getMessage());
        return false;
    }
}

function fetchOne($conn, $sql, $params = []) {
    try {
        $stmt = executeQuery($conn, $sql, $params);
        if (!$stmt) {
            return false;
        }
        
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        $stmt->close();
        return $data;
    } catch (Exception $e) {
        error_log("Fetch one error: " . $e->getMessage());
        return false;
    }
}

/**
 * True if a column exists in a table (e.g. optional DB migration not yet on all servers).
 * $table: alphanumeric + underscore only (internal fixed names only).
 */
function dbTableHasColumn($conn, $table, $column) {
    if (!$conn) {
        return false;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($t === '' || $column === '') {
        return false;
    }
    $c = $conn->real_escape_string((string) $column);
    $q = @$conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $q && $q->num_rows > 0;
}

/**
 * Whether a table exists (internal fixed table names only).
 */
function btb_db_table_exists($conn, string $table): bool {
    if (!$conn) {
        return false;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '') {
        return false;
    }
    $r = @$conn->query("SHOW TABLES LIKE '{$t}'");
    return $r && $r->num_rows > 0;
}

/**
 * room_page_settings: DB column => content_settings-shaped key (one row per room_key).
 * Run ensure_room_page_settings_extended_columns.php once so optional columns exist on the server.
 *
 * @return array<string, array<string, string>>
 */
function btb_room_page_settings_column_map(): array {
    return [
        'room-basement' => [
            'page_title' => 'room_basement_title',
            'subtitle' => 'room_basement_subtitle',
            'description' => 'room_basement_description',
            'note' => 'room_basement_note',
            'banner_image_url' => 'room_basement_banner_image_url',
            'gallery_json' => 'room_basement_gallery',
            'common_gallery_json' => 'room_basement_common_gallery',
            'price_prefix' => 'room_basement_price_prefix',
            'price_amount' => 'room_basement_price_amount',
            'price_suffix' => 'room_basement_price_suffix',
            'capacity' => 'room_basement_capacity',
            'gallery_section_title' => 'room_basement_gallery_section_title',
            'common_gallery_section_title' => 'room_basement_common_gallery_section_title',
        ],
        'room-ground-queen' => [
            'page_title' => 'room_ground_queen_title',
            'subtitle' => 'room_ground_queen_subtitle',
            'description' => 'room_ground_queen_description',
            'note' => 'room_ground_queen_note',
            'banner_image_url' => 'room_ground_queen_banner_image_url',
            'gallery_json' => 'room_ground_queen_gallery',
            'common_gallery_json' => 'room_ground_queen_common_gallery',
            'price_prefix' => 'room_ground_queen_price_prefix',
            'price_amount' => 'room_ground_queen_price_amount',
            'price_suffix' => 'room_ground_queen_price_suffix',
            'capacity' => 'room_ground_queen_capacity',
            'gallery_section_title' => 'room_ground_queen_gallery_section_title',
            'common_gallery_section_title' => 'room_ground_queen_common_gallery_section_title',
        ],
        'room-ground-twin' => [
            'page_title' => 'room_ground_twin_title',
            'subtitle' => 'room_ground_twin_subtitle',
            'description' => 'room_ground_twin_description',
            'note' => 'room_ground_twin_note',
            'banner_image_url' => 'room_ground_twin_banner_image_url',
            'gallery_json' => 'room_ground_twin_gallery',
            'common_gallery_json' => 'room_ground_twin_common_gallery',
            'price_prefix' => 'room_ground_twin_price_prefix',
            'price_amount' => 'room_ground_twin_price_amount',
            'price_suffix' => 'room_ground_twin_price_suffix',
            'capacity' => 'room_ground_twin_capacity',
            'gallery_section_title' => 'room_ground_twin_gallery_section_title',
            'common_gallery_section_title' => 'room_ground_twin_common_gallery_section_title',
        ],
        'room-second' => [
            'page_title' => 'room_second_title',
            'subtitle' => 'room_second_subtitle',
            'description' => 'room_second_description',
            'note' => 'room_second_note',
            'banner_image_url' => 'room_second_banner_image_url',
            'gallery_json' => 'room_second_gallery',
            'common_gallery_json' => 'room_second_common_gallery',
            'price_prefix' => 'room_second_price_prefix',
            'price_amount' => 'room_second_price_amount',
            'price_suffix' => 'room_second_price_suffix',
            'capacity' => 'room_second_capacity',
            'gallery_section_title' => 'room_second_gallery_section_title',
            'common_gallery_section_title' => 'room_second_common_gallery_section_title',
        ],
    ];
}

/**
 * @return array<string, true>
 */
function btb_room_page_settings_existing_columns($conn): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!$conn || !btb_db_table_exists($conn, 'room_page_settings')) {
        return $cache;
    }
    $r = @$conn->query('SHOW COLUMNS FROM `room_page_settings`');
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            if (!empty($row['Field'])) {
                $cache[$row['Field']] = true;
            }
        }
    }

    return $cache;
}

/**
 * Maps POST/content keys that are stored per-room in room_page_settings.
 *
 * @return array{0: string, 1: string}|null [room_key, column_name]
 */
function btb_room_content_key_to_room_page_slot(string $postKey): ?array {
    static $inv = null;
    if ($inv === null) {
        $inv = [];
        foreach (btb_room_page_settings_column_map() as $rk => $pairs) {
            foreach ($pairs as $dbc => $ck) {
                $inv[$ck] = [$rk, $dbc];
            }
        }
    }

    return $inv[$postKey] ?? null;
}

/**
 * Whether save_content should skip mirroring this field into content_settings because room_page_settings owns it.
 */
function btb_room_field_skip_content_settings_for_room_page($conn, string $field): bool {
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'room_page_settings')) {
        return false;
    }
    $slot = btb_room_content_key_to_room_page_slot($field);
    if ($slot === null || !function_exists('dbTableHasColumn')) {
        return false;
    }

    return dbTableHasColumn($conn, 'room_page_settings', $slot[1]);
}

/** @return list<string> */
function btb_room_nightly_price_content_keys(): array {
    $keys = [];
    foreach (btb_room_price_column_map() as $m) {
        foreach (['prefix', 'amount', 'suffix', 'legacy'] as $x) {
            $keys[$m[$x]] = true;
        }
    }

    return array_keys($keys);
}

/** @return list<string> */
function btb_room_card_price_content_keys(): array {
    return [
        'room_basement_card_price',
        'room_ground_queen_card_price',
        'room_ground_twin_card_price',
        'room_second_card_price',
    ];
}

/**
 * Single source: nightly rates in room_page_settings (requires split columns on that table).
 */
function btb_room_price_room_page_settings_only_active($conn): bool {
    if (!defined('BTB_ROOM_PRICE_ROOM_PAGE_SETTINGS_ONLY') || !BTB_ROOM_PRICE_ROOM_PAGE_SETTINGS_ONLY) {
        return false;
    }
    if (!$conn || !btb_db_table_exists($conn, 'room_page_settings')) {
        return false;
    }
    $cols = btb_room_page_settings_existing_columns($conn);

    return !empty($cols['price_prefix']) || !empty($cols['price_amount']) || !empty($cols['price_suffix']);
}

/** Skip legacy HTML column room_*_price on content_settings when room_page is canonical. */
function btb_room_field_skip_content_settings_for_nightly_legacy($conn, string $field): bool {
    if (!btb_room_price_room_page_settings_only_active($conn)) {
        return false;
    }

    return (bool) preg_match('/^room_(basement|ground_queen|ground_twin|second)_price$/', $field);
}

/** Skip room_*_card_price on content_settings when that column exists on room_cards (price on the card = from the detail page; do not duplicate columns). */
function btb_room_field_skip_content_settings_for_room_card_price_column($conn, string $field): bool {
    if (!in_array($field, btb_room_card_price_content_keys(), true)) {
        return false;
    }
    if (!$conn || !btb_db_table_exists($conn, 'room_cards_settings')) {
        return false;
    }

    return dbTableHasColumn($conn, 'room_cards_settings', $field);
}

/** Unified skip for save_content: room_page columns, nightly legacy, or card price on room_cards. */
function btb_room_field_skip_content_settings_for_room_pricing($conn, string $field): bool {
    if (btb_room_field_skip_content_settings_for_room_page($conn, $field)) {
        return true;
    }
    if (btb_room_field_skip_content_settings_for_nightly_legacy($conn, $field)) {
        return true;
    }
    if (btb_room_field_skip_content_settings_for_room_card_price_column($conn, $field)) {
        return true;
    }

    return false;
}

/**
 * Merge one CMS row (id = 1) into a content-shaped array. Section table wins for every column it has.
 * $table: fixed internal name only (alphanumeric + underscore).
 */
function btb_merge_cms_table_row_id1_into_data($conn, string $table, array &$data): void {
    if (!$conn || !is_array($data)) {
        return;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '' || !btb_db_table_exists($conn, $t)) {
        return;
    }
    $r = @$conn->query("SELECT * FROM `{$t}` WHERE id = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        return;
    }
    $row = $r->fetch_assoc();
    $skip = ['id', 'created_at', 'updated_at'];
    foreach ($row as $k => $v) {
        if (in_array($k, $skip, true)) {
            continue;
        }
        $data[$k] = $v;
    }
}

/**
 * Merge special_settings id=1 into $data. Empty/null canonical values must not wipe non-empty copies still
 * in content_settings (legacy rows often have text only in content_settings while special_settings exists with NULLs).
 */
/**
 * Second Specials panel (same shape as offer + three cards). Columns are created on demand via
 * btb_ensure_special_block2_columns() — no manual migration script.
 *
 * @return array<string, string> column_name => MySQL column definition
 */
function btb_special_block2_column_sql_definitions(): array {
    return [
        'special_b2_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'special_b2_offer_title' => 'VARCHAR(512) DEFAULT NULL',
        'special_b2_offer_main_text' => 'MEDIUMTEXT NULL',
        'special_b2_offer_rooms_cta_label' => 'VARCHAR(512) DEFAULT NULL',
        'special_b2_pools_title' => 'VARCHAR(512) DEFAULT NULL',
        'special_b2_pools_description_1' => 'MEDIUMTEXT NULL',
        'special_b2_pools_description_2' => 'MEDIUMTEXT NULL',
        'special_b2_pools_image_url' => 'VARCHAR(1024) DEFAULT NULL',
        'special_b2_dining_title' => 'VARCHAR(512) DEFAULT NULL',
        'special_b2_dining_description_1' => 'MEDIUMTEXT NULL',
        'special_b2_dining_image_url' => 'VARCHAR(1024) DEFAULT NULL',
        'special_b2_extra_title' => 'VARCHAR(512) DEFAULT NULL',
        'special_b2_extra_description_1' => 'MEDIUMTEXT NULL',
        'special_b2_extra_description_2' => 'MEDIUMTEXT NULL',
        'special_b2_extra_image_url' => 'VARCHAR(1024) DEFAULT NULL',
    ];
}

/**
 * Ensure block-2 columns exist on special_settings and content_settings (best-effort; logs ALTER failures).
 */
function btb_ensure_special_block2_columns(mysqli $conn): void {
    if (!$conn || !function_exists('btb_db_table_exists')) {
        return;
    }
    $defs = btb_special_block2_column_sql_definitions();
    foreach (['special_settings', 'content_settings'] as $table) {
        if (!btb_db_table_exists($conn, $table)) {
            continue;
        }
        foreach ($defs as $col => $ddl) {
            $esc = $conn->real_escape_string($col);
            $chk = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$esc}'");
            if ($chk && $chk->num_rows > 0) {
                continue;
            }
            $colSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if ($colSafe === '') {
                continue;
            }
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$colSafe}` {$ddl}";
            if (!@$conn->query($sql)) {
                error_log('btb_ensure_special_block2_columns: ALTER failed ' . $table . '.' . $colSafe . ': ' . $conn->error);
            } else {
                error_log('btb_ensure_special_block2_columns: added ' . $table . '.' . $colSafe);
            }
        }
    }
}

/** JSON array of extra Specials panels (same shape as legacy block 2). Max 10. */
function btb_special_addon_panels_json_column_name(): string {
    return 'special_addon_panels_json';
}

function btb_ensure_special_addon_panels_json_column(mysqli $conn): void {
    if (!$conn || !function_exists('btb_db_table_exists')) {
        return;
    }
    $col = btb_special_addon_panels_json_column_name();
    $ddl = 'MEDIUMTEXT NULL';
    foreach (['special_settings', 'content_settings'] as $table) {
        if (!btb_db_table_exists($conn, $table)) {
            continue;
        }
        $esc = $conn->real_escape_string($col);
        $chk = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$esc}'");
        if ($chk && $chk->num_rows > 0) {
            continue;
        }
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$ddl}";
        if (!@$conn->query($sql)) {
            error_log('btb_ensure_special_addon_panels_json_column: ALTER failed ' . $table . ': ' . $conn->error);
        }
    }
}

function btb_special_addon_panels_blank_row(): array {
    return [
        'id' => '',
        'offerTitle' => '',
        'offerMainText' => '',
        'offerRoomsCtaLabel' => 'Choose your room',
        'poolsTitle' => '',
        'poolsDescription1' => '',
        'poolsDescription2' => '',
        'poolsImageUrl' => '',
        'diningTitle' => '',
        'diningDescription1' => '',
        'diningImageUrl' => '',
        'extraTitle' => '',
        'extraDescription1' => '',
        'extraDescription2' => '',
        'extraImageUrl' => '',
    ];
}

function btb_special_addon_panels_sanitize_assoc(?array $row): array {
    $b = btb_special_addon_panels_blank_row();
    if (!is_array($row)) {
        $b['id'] = 'blk_' . bin2hex(random_bytes(8));
        return $b;
    }
    $id = trim((string) ($row['id'] ?? ''));
    if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id)) {
        $id = 'blk_' . bin2hex(random_bytes(8));
    }
    $b['id'] = $id;
    $keys = [
        'offerTitle', 'offerMainText', 'offerRoomsCtaLabel', 'poolsTitle', 'poolsDescription1', 'poolsDescription2',
        'poolsImageUrl', 'diningTitle', 'diningDescription1', 'diningImageUrl', 'extraTitle', 'extraDescription1',
        'extraDescription2', 'extraImageUrl',
    ];
    foreach ($keys as $k) {
        $b[$k] = trim((string) ($row[$k] ?? ''));
    }
    if ($b['offerRoomsCtaLabel'] === '') {
        $b['offerRoomsCtaLabel'] = 'Choose your room';
    }
    return $b;
}

function btb_special_addon_panels_normalize_list(?array $list): array {
    if (!is_array($list)) {
        return [];
    }
    $max = 10;
    $out = [];
    foreach ($list as $item) {
        if (count($out) >= $max) {
            break;
        }
        $out[] = btb_special_addon_panels_sanitize_assoc(is_array($item) ? $item : []);
    }
    return $out;
}

function btb_special_addon_panels_normalize_json_string(string $raw): string {
    $t = trim($raw);
    if ($t === '' || $t === 'null') {
        return '[]';
    }
    $d = json_decode($t, true);
    if (!is_array($d)) {
        return '[]';
    }
    return json_encode(btb_special_addon_panels_normalize_list($d), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function btb_special_addon_panels_from_legacy_b2(array $content): ?array {
    $enabled = (int) ($content['special_b2_enabled'] ?? 0) === 1;
    $keys = [
        'special_b2_offer_title', 'special_b2_offer_main_text', 'special_b2_pools_title', 'special_b2_pools_description_1',
        'special_b2_dining_title', 'special_b2_dining_description_1', 'special_b2_extra_title', 'special_b2_extra_description_1',
        'special_b2_pools_image_url', 'special_b2_dining_image_url', 'special_b2_extra_image_url',
    ];
    $any = false;
    foreach ($keys as $k) {
        if (trim((string) ($content[$k] ?? '')) !== '') {
            $any = true;
            break;
        }
    }
    if (!$enabled && !$any) {
        return null;
    }
    return btb_special_addon_panels_sanitize_assoc([
        'id' => 'legacy-b2',
        'offerTitle' => (string) ($content['special_b2_offer_title'] ?? ''),
        'offerMainText' => (string) ($content['special_b2_offer_main_text'] ?? ''),
        'offerRoomsCtaLabel' => (string) ($content['special_b2_offer_rooms_cta_label'] ?? ''),
        'poolsTitle' => (string) ($content['special_b2_pools_title'] ?? ''),
        'poolsDescription1' => (string) ($content['special_b2_pools_description_1'] ?? ''),
        'poolsDescription2' => (string) ($content['special_b2_pools_description_2'] ?? ''),
        'poolsImageUrl' => (string) ($content['special_b2_pools_image_url'] ?? ''),
        'diningTitle' => (string) ($content['special_b2_dining_title'] ?? ''),
        'diningDescription1' => (string) ($content['special_b2_dining_description_1'] ?? ''),
        'diningImageUrl' => (string) ($content['special_b2_dining_image_url'] ?? ''),
        'extraTitle' => (string) ($content['special_b2_extra_title'] ?? ''),
        'extraDescription1' => (string) ($content['special_b2_extra_description_1'] ?? ''),
        'extraDescription2' => (string) ($content['special_b2_extra_description_2'] ?? ''),
        'extraImageUrl' => (string) ($content['special_b2_extra_image_url'] ?? ''),
    ]);
}

function btb_special_addon_panels_decode_from_content(array $content): array {
    $col = btb_special_addon_panels_json_column_name();
    if (array_key_exists($col, $content)) {
        $raw = trim((string) ($content[$col] ?? ''));
        if ($raw !== '' && strcasecmp($raw, 'null') !== 0) {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                return btb_special_addon_panels_normalize_list($d);
            }
        }
    }
    $leg = btb_special_addon_panels_from_legacy_b2($content);
    return $leg !== null ? [$leg] : [];
}

function btb_merge_special_settings_into_data($conn, array &$data): void {
    if (!$conn || !is_array($data)) {
        return;
    }
    $t = 'special_settings';
    if (!btb_db_table_exists($conn, $t)) {
        return;
    }
    $r = @$conn->query("SELECT * FROM `{$t}` WHERE id = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        return;
    }
    $row = $r->fetch_assoc();
    $skip = ['id', 'created_at', 'updated_at'];
    foreach ($row as $k => $v) {
        if (in_array($k, $skip, true)) {
            continue;
        }
        $incoming = $v === null ? '' : trim((string) $v);
        if ($incoming !== '') {
            $data[$k] = $v;
            continue;
        }
        $existing = isset($data[$k]) ? trim((string) ($data[$k] ?? '')) : '';
        if ($existing === '') {
            $data[$k] = $v === null ? '' : (string) $v;
        }
    }
}

/**
 * Merge about_settings id=1 into $data. Keys mirrored from explore_parks_settings are only overwritten when non-empty,
 * so an empty legacy column in about_settings does not erase data merged from explore_parks_settings in api.php.
 */
function btb_merge_about_settings_into_data_without_clearing_explore_parks($conn, array &$data): void {
    if (!$conn || !is_array($data)) {
        return;
    }
    $t = 'about_settings';
    if (!btb_db_table_exists($conn, $t)) {
        return;
    }
    $r = @$conn->query("SELECT * FROM `{$t}` WHERE id = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        return;
    }
    $row = $r->fetch_assoc();
    $skip = ['id', 'created_at', 'updated_at'];
    $parksMirrored = [
        'about_parks_title', 'about_parks_intro', 'about_parks_list',
        'about_parks_map_lat', 'about_parks_map_lng', 'about_parks_hero_image_url',
        'about_parks_gallery', 'about_parks_cards',
    ];
    foreach ($row as $k => $v) {
        if (in_array($k, $skip, true)) {
            continue;
        }
        if (in_array($k, $parksMirrored, true)) {
            if ($v === null || trim((string) $v) === '') {
                continue;
            }
        }
        $data[$k] = $v;
    }
}

/**
 * POST keys for the provincial parks block stored in explore_parks_settings (admin sends about_parks_*).
 *
 * @return list<string>
 */
function btb_explore_parks_post_field_names(): array {
    return [
        'about_parks_title',
        'about_parks_intro',
        'about_parks_list',
        'about_parks_map_lat',
        'about_parks_map_lng',
        'about_parks_hero_image_url',
        'about_parks_gallery',
        'about_parks_cards',
    ];
}

/**
 * Read path: merge explore_settings, explore_parks_settings (into about_parks_*), explore_community_extra into $data.
 * Kept in common.php with get_content so public pages can rely on btb_merge_phase1_canonical_into_content_row alone.
 */
function btb_merge_explore_canonical_into_content_row($conn, array &$data): void {
    if (!$conn || !is_array($data)) {
        return;
    }

    if (btb_db_table_exists($conn, 'explore_settings')) {
        $exR = @$conn->query('SELECT * FROM explore_settings WHERE id = 1');
        if ($exR && $exR->num_rows > 0) {
            $exRow = $exR->fetch_assoc();
            if (is_array($exRow)) {
                foreach ($exRow as $k => $v) {
                    if ($k === 'id') {
                        continue;
                    }
                    if (strpos((string) $k, 'explore_') === 0) {
                        $data[$k] = $v;
                    }
                }
            }
            error_log('btb_merge_explore_canonical_into_content_row: merged explore_settings');
        }
    }

    if (btb_db_table_exists($conn, 'explore_parks_settings')) {
        $epR = @$conn->query("SELECT title, intro, parks_list, map_lat, map_lng, hero_image_url, gallery, parks_cards FROM explore_parks_settings WHERE id = 1");
        if ($epR && $epR->num_rows > 0) {
            $epRow = $epR->fetch_assoc();
            if (is_array($epRow)) {
                if (array_key_exists('title', $epRow) && $epRow['title'] !== null && trim((string) $epRow['title']) !== '') {
                    $data['about_parks_title'] = $epRow['title'];
                }
                if (array_key_exists('intro', $epRow) && $epRow['intro'] !== null && trim((string) $epRow['intro']) !== '') {
                    $data['about_parks_intro'] = $epRow['intro'];
                }
                if (array_key_exists('parks_list', $epRow) && $epRow['parks_list'] !== null && trim((string) $epRow['parks_list']) !== '') {
                    $data['about_parks_list'] = $epRow['parks_list'];
                }
                if (array_key_exists('map_lat', $epRow)) {
                    $data['about_parks_map_lat'] = $epRow['map_lat'];
                }
                if (array_key_exists('map_lng', $epRow)) {
                    $data['about_parks_map_lng'] = $epRow['map_lng'];
                }
                if (array_key_exists('hero_image_url', $epRow)) {
                    $data['about_parks_hero_image_url'] = $epRow['hero_image_url'];
                }
                if (array_key_exists('gallery', $epRow)) {
                    $data['about_parks_gallery'] = $epRow['gallery'];
                }
                if (array_key_exists('parks_cards', $epRow)) {
                    $data['about_parks_cards'] = $epRow['parks_cards'];
                }
            }
            error_log('btb_merge_explore_canonical_into_content_row: merged explore_parks_settings');
        }
    }

    if (btb_db_table_exists($conn, 'explore_community_extra')) {
        $ecR = @$conn->query('SELECT * FROM explore_community_extra WHERE id = 1');
        if ($ecR && $ecR->num_rows > 0) {
            $ecRow = $ecR->fetch_assoc();
            if (is_array($ecRow)) {
                foreach ($ecRow as $k => $v) {
                    if ($k === 'id' || $v === null) {
                        continue;
                    }
                    if ($k === 'about_nelson_image_url') {
                        $cur = trim((string) ($data['about_nelson_image_url'] ?? ''));
                        if ($cur === '' && trim((string) $v) !== '') {
                            $data['about_nelson_image_url'] = $v;
                        }
                    } elseif (strpos((string) $k, 'about_kaslo_') === 0 || strpos((string) $k, 'about_crawford_') === 0 || strpos((string) $k, 'about_museum_') === 0) {
                        $data[$k] = $v;
                    } elseif (strpos((string) $k, 'explore_') === 0) {
                        $data[$k] = $v;
                    }
                }
            }
            error_log('btb_merge_explore_canonical_into_content_row: merged explore_community_extra');
        }
    }
}

/**
 * Write path: persist Explore-related POST into explore_* tables; optional fallbacks onto content_settings $fields/$values/$types.
 *
 * @param array<int,string>|null $fieldsRef
 * @param array<int,mixed>|null $valuesRef
 * @return array{explore_settings_saved:bool,explore_parks_saved:bool,explore_community_extra_saved:bool}
 */
function btb_dual_write_explore_canonical_from_post($conn, &$fieldsRef = null, &$valuesRef = null, &$typesRef = null): array {
    $out = [
        'explore_settings_saved' => false,
        'explore_parks_saved' => false,
        'explore_community_extra_saved' => false,
    ];
    if (!$conn || !isset($_POST) || !is_array($_POST)) {
        return $out;
    }

    $appendCs = static function ($col, $val) use (&$fieldsRef, &$valuesRef, &$typesRef, $conn): void {
        if (!is_array($fieldsRef) || !is_array($valuesRef) || !is_string($typesRef)) {
            return;
        }
        $esc = $conn->real_escape_string($col);
        $cEx = @$conn->query("SHOW COLUMNS FROM content_settings LIKE '{$esc}'");
        if ($cEx && $cEx->num_rows > 0) {
            $fieldsRef[] = "`{$col}` = ?";
            $valuesRef[] = $val;
            $typesRef .= 's';
            error_log("btb_dual_write_explore_canonical_from_post: Explore fallback to content_settings.{$col}");
        }
    };

    $exploreSettingsTableExists = btb_db_table_exists($conn, 'explore_settings');
    $esKeys = [
        'explore_hero_title',
        'explore_hero_subtitle',
        'explore_hero_image_url',
        'explore_accommodation_title',
        'explore_accommodation_description',
        'explore_accommodation_image_url',
        'explore_gallery_overlay_community',
        'explore_gallery_overlay_culture',
        'explore_gallery_overlay_park',
        'explore_gallery_overlay_activity',
        'explore_gallery_overlay_stay',
    ];
    $exploreSettingsColNames = [];
    if ($exploreSettingsTableExists) {
        if (function_exists('dbTableHasColumn')) {
            foreach ([
                'explore_gallery_overlay_community' => 'TEXT',
                'explore_gallery_overlay_culture' => 'TEXT',
                'explore_gallery_overlay_park' => 'TEXT',
                'explore_gallery_overlay_activity' => 'TEXT',
                'explore_gallery_overlay_stay' => 'TEXT',
            ] as $esColName => $esColType) {
                if (!dbTableHasColumn($conn, 'explore_settings', $esColName)) {
                    @$conn->query("ALTER TABLE `explore_settings` ADD COLUMN `{$esColName}` {$esColType} NULL");
                }
            }
        }
        $esColChk = $conn->query('SHOW COLUMNS FROM explore_settings');
        if ($esColChk) {
            while ($cRow = $esColChk->fetch_assoc()) {
                if (!empty($cRow['Field'])) {
                    $exploreSettingsColNames[$cRow['Field']] = true;
                }
            }
        }
    }

    if ($exploreSettingsTableExists) {
        $esToUpdate = [];
        foreach ($esKeys as $k) {
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            if (empty($exploreSettingsColNames[$k])) {
                error_log("btb_dual_write_explore_canonical_from_post: skipping explore_settings, column missing: {$k}");
                continue;
            }
            $esToUpdate[$k] = $_POST[$k];
        }
        if (!empty($esToUpdate)) {
            $esRowChk = $conn->query('SELECT id FROM explore_settings WHERE id = 1');
            if (!$esRowChk || $esRowChk->num_rows === 0) {
                $conn->query('INSERT INTO explore_settings (id) VALUES (1)');
            }
            $esSets = [];
            $esVals = [];
            $esTypes = '';
            foreach ($esToUpdate as $k => $v) {
                $esSets[] = "`{$k}` = ?";
                $esVals[] = $v;
                $esTypes .= 's';
            }
            $esSql = 'UPDATE explore_settings SET ' . implode(', ', $esSets) . ' WHERE id = 1';
            $esStmt = $conn->prepare($esSql);
            if ($esStmt) {
                $esStmt->bind_param($esTypes, ...$esVals);
                if ($esStmt->execute()) {
                    $out['explore_settings_saved'] = true;
                    error_log('btb_dual_write_explore_canonical_from_post: saved explore_settings: ' . implode(', ', array_keys($esToUpdate)));
                } else {
                    error_log('btb_dual_write_explore_canonical_from_post: explore_settings update failed: ' . $esStmt->error);
                }
                $esStmt->close();
            }
        }
    }

    foreach ($esKeys as $ef) {
        if (!isset($_POST[$ef])) {
            continue;
        }
        if ($exploreSettingsTableExists && !empty($exploreSettingsColNames[$ef])) {
            continue;
        }
        $appendCs($ef, $_POST[$ef]);
    }

    $exploreParksTableExists = btb_db_table_exists($conn, 'explore_parks_settings');
    if ($exploreParksTableExists) {
        $epPostMap = [
            'about_parks_title' => 'title',
            'about_parks_intro' => 'intro',
            'about_parks_list' => 'parks_list',
            'about_parks_map_lat' => 'map_lat',
            'about_parks_map_lng' => 'map_lng',
            'about_parks_hero_image_url' => 'hero_image_url',
            'about_parks_gallery' => 'gallery',
            'about_parks_cards' => 'parks_cards',
        ];
        $epToUpdate = [];
        foreach ($epPostMap as $postKey => $col) {
            if (array_key_exists($postKey, $_POST)) {
                $epToUpdate[$col] = $_POST[$postKey];
            }
        }
        if (!empty($epToUpdate)) {
            $epRowChk = $conn->query('SELECT id FROM explore_parks_settings WHERE id = 1');
            if (!$epRowChk || $epRowChk->num_rows === 0) {
                $conn->query('INSERT INTO explore_parks_settings (id) VALUES (1)');
            }
            $epSets = [];
            $epVals = [];
            $epTypes = '';
            foreach ($epToUpdate as $col => $v) {
                $epSets[] = "`{$col}` = ?";
                $epVals[] = $v;
                $epTypes .= 's';
            }
            $epSql = 'UPDATE explore_parks_settings SET ' . implode(', ', $epSets) . ' WHERE id = 1';
            $epStmt = $conn->prepare($epSql);
            if ($epStmt) {
                $epStmt->bind_param($epTypes, ...$epVals);
                if ($epStmt->execute()) {
                    $out['explore_parks_saved'] = true;
                    error_log('btb_dual_write_explore_canonical_from_post: saved explore_parks_settings: ' . implode(', ', array_keys($epToUpdate)));
                } else {
                    error_log('btb_dual_write_explore_canonical_from_post: explore_parks_settings update failed: ' . $epStmt->error);
                }
                $epStmt->close();
            }
        }
    }

    $exploreCommunityExtraTableExists = btb_db_table_exists($conn, 'explore_community_extra');
    if ($exploreCommunityExtraTableExists) {
        $ecExtraKeys = [
            'about_nelson_image_url',
            'about_kaslo_title',
            'about_kaslo_distance',
            'about_kaslo_description',
            'about_kaslo_image_url',
            'about_kaslo_gallery',
            'about_crawford_title',
            'about_crawford_distance',
            'about_crawford_description',
            'about_crawford_image_url',
            'about_crawford_gallery',
            'about_museum_title',
            'about_museum_distance',
            'about_museum_description',
            'about_museum_image_url',
            'about_museum_gallery',
            'explore_communities_h2',
            'explore_culture_h2',
            'explore_parks_h2',
            'explore_activities_h2',
            'explore_communities_intro',
            'explore_culture_intro',
            'explore_activities_intro',
            'explore_communities_cards',
            'explore_culture_cards',
            'explore_activities_cards',
        ];
        $ecToUpdate = [];
        foreach ($ecExtraKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                $ecToUpdate[$k] = $_POST[$k];
            }
        }
        if (!empty($ecToUpdate)) {
            $ecRowChk = $conn->query('SELECT id FROM explore_community_extra WHERE id = 1');
            if (!$ecRowChk || $ecRowChk->num_rows === 0) {
                $conn->query('INSERT IGNORE INTO explore_community_extra (id) VALUES (1)');
            }
            $ecSets = [];
            $ecVals = [];
            $ecTypes = '';
            foreach ($ecToUpdate as $col => $v) {
                $ecSets[] = "`{$col}` = ?";
                $ecVals[] = $v;
                $ecTypes .= 's';
            }
            $ecSql = 'UPDATE explore_community_extra SET ' . implode(', ', $ecSets) . ' WHERE id = 1';
            $ecStmt = $conn->prepare($ecSql);
            if ($ecStmt) {
                $ecStmt->bind_param($ecTypes, ...$ecVals);
                if ($ecStmt->execute()) {
                    $out['explore_community_extra_saved'] = true;
                    error_log('btb_dual_write_explore_canonical_from_post: saved explore_community_extra: ' . implode(', ', array_keys($ecToUpdate)));
                } else {
                    error_log('btb_dual_write_explore_canonical_from_post: explore_community_extra update failed: ' . $ecStmt->error);
                }
                $ecStmt->close();
            } else {
                error_log('btb_dual_write_explore_canonical_from_post: explore_community_extra prepare failed: ' . ($conn->error ?: 'unknown'));
            }
        }
    }

    $exploreCardJsonKeys = ['explore_communities_cards', 'explore_culture_cards', 'explore_activities_cards'];
    foreach ($exploreCardJsonKeys as $ek) {
        if (!array_key_exists($ek, $_POST)) {
            continue;
        }
        if (!empty($out['explore_community_extra_saved'])) {
            continue;
        }
        $esc = $conn->real_escape_string($ek);
        $cEx = @$conn->query("SHOW COLUMNS FROM content_settings LIKE '{$esc}'");
        if ($cEx && $cEx->num_rows > 0 && is_array($fieldsRef) && is_array($valuesRef) && is_string($typesRef)) {
            $fieldsRef[] = "`{$ek}` = ?";
            $valuesRef[] = $_POST[$ek];
            $typesRef .= 's';
            error_log("btb_dual_write_explore_canonical_from_post: Explore card JSON fallback to content_settings.{$ek}");
        } else {
            error_log("btb_dual_write_explore_canonical_from_post: Explore card JSON not saved (no explore_community_extra save and no content_settings.{$ek} column). Run create_explore_community_extra_table.php on the server.");
        }
    }

    return $out;
}

/**
 * Floorplan column names merged from floorplan_settings into a content-shaped row.
 *
 * @return list<string>
 */
function btb_floorplan_settings_content_keys(): array {
    return [
        'floorplan_title', 'floorplan_subtitle',
        'basement_subtitle', 'basement_description', 'basement_image_url', 'basement_gallery',
        'basement_gallery_overlay_text',
        'ground_subtitle', 'ground_description', 'ground_queen_image', 'ground_twin_image', 'ground_image_url',
        'ground_gallery', 'ground_gallery_overlay_text',
        'loft_subtitle', 'loft_description', 'loft_image_url', 'loft_gallery', 'loft_gallery_overlay_text',
    ];
}

/**
 * Merge floorplan_settings id=1 into $data; set ground_image_url from ground_queen_image when missing.
 */
function btb_merge_floorplan_settings_into_data($conn, array &$data): void {
    btb_merge_cms_table_row_id1_into_data($conn, 'floorplan_settings', $data);
    if (!is_array($data)) {
        return;
    }
    $gq = trim((string) ($data['ground_queen_image'] ?? ''));
    $gu = trim((string) ($data['ground_image_url'] ?? ''));
    if ($gq !== '' && $gu === '') {
        $data['ground_image_url'] = $data['ground_queen_image'];
    }
}

/**
 * Extract floorplan-shaped array from a merged content row (after btb_merge_phase1_canonical_into_content_row).
 *
 * @param array<string,mixed> $content
 * @return array<string,mixed>
 */
function btb_floorplan_slice_from_content_row(array $content): array {
    $out = [];
    foreach (btb_floorplan_settings_content_keys() as $k) {
        if (array_key_exists($k, $content)) {
            $out[$k] = $content[$k];
        }
    }
    $gq = trim((string) ($out['ground_queen_image'] ?? ''));
    $gu = trim((string) ($out['ground_image_url'] ?? ''));
    if ($gq !== '' && $gu === '') {
        $out['ground_image_url'] = $out['ground_queen_image'];
    }
    return $out;
}

/**
 * Shape a floorplan_settings row for the admin get_floorplan JSON (underscore keys, string defaults).
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function btb_floorplan_api_payload_from_row(array $row): array {
    if (isset($row['ground_queen_image']) && (!isset($row['ground_image_url']) || trim((string) ($row['ground_image_url'] ?? '')) === '')) {
        $row['ground_image_url'] = $row['ground_queen_image'];
    }
    $noDbRow = ($row === []);
    $plan = 'assets/plan.jpg';
    return [
        'floorplan_title' => $row['floorplan_title'] ?? 'Common areas',
        'floorplan_subtitle' => $row['floorplan_subtitle'] ?? 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.',
        'basement_subtitle' => $row['basement_subtitle'] ?? ($noDbRow ? 'Private floor with a separate entrance.' : ''),
        'basement_description' => $row['basement_description'] ?? ($noDbRow ? 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.' : ''),
        'basement_image_url' => $row['basement_image_url'] ?? ($noDbRow ? $plan : ''),
        'ground_subtitle' => $row['ground_subtitle'] ?? ($noDbRow ? 'Open space with a separate entrance.' : ''),
        'ground_description' => $row['ground_description'] ?? ($noDbRow ? 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.' : ''),
        'ground_queen_image' => $row['ground_queen_image'] ?? ($noDbRow ? $plan : ''),
        'ground_image_url' => $row['ground_image_url'] ?? $row['ground_queen_image'] ?? ($noDbRow ? $plan : ''),
        'ground_twin_image' => $row['ground_twin_image'] ?? '',
        'basement_gallery' => $row['basement_gallery'] ?? '[]',
        'ground_gallery' => $row['ground_gallery'] ?? '[]',
        'loft_gallery' => $row['loft_gallery'] ?? '[]',
        'loft_subtitle' => $row['loft_subtitle'] ?? 'Multifunctional spaces & small cinema',
        'loft_description' => $row['loft_description'] ?? 'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
        'loft_image_url' => $row['loft_image_url'] ?? ($noDbRow ? $plan : ''),
        'basement_gallery_overlay_text' => $row['basement_gallery_overlay_text'] ?? '',
        'ground_gallery_overlay_text' => $row['ground_gallery_overlay_text'] ?? '',
        'loft_gallery_overlay_text' => $row['loft_gallery_overlay_text'] ?? '',
    ];
}

/**
 * Default homepage guest reviews (seed copy + structure for admin pad and index fallbacks).
 *
 * @return array{section_title: string, section_subtitle: string, vrbo: list<array{name: string, rating: int, text: string}>, airbnb: list<array{name: string, rating: int, text: string}>}
 */
function btb_guest_reviews_default_payload(): array {
    return [
        'section_title' => 'Guest reviews',
        'section_subtitle' => 'What recent guests have shared on Vrbo and Airbnb.',
        'vrbo' => [
            ['name' => 'Emily R.', 'rating' => 5, 'text' => 'A wonderful stay. The home is even better than the photos, surrounded by trees and so peaceful. We would happily return.'],
            ['name' => 'James K.', 'rating' => 5, 'text' => 'Spotless, spacious, and thoughtfully equipped. The hosts were warm and the location is perfect for exploring Nelson.'],
            ['name' => 'Olivia T.', 'rating' => 5, 'text' => 'Loved the quiet setting and the comfortable beds. Mornings on the deck with coffee were a highlight.'],
            ['name' => 'Michael P.', 'rating' => 4, 'text' => 'Great for a group retreat. Kitchen and common areas are ideal for cooking together. Minor wish: faster Wi-Fi, but that is a small point in such a restful place.'],
            ['name' => 'Anna L.', 'rating' => 5, 'text' => 'Truly a place to slow down. Every detail made us feel welcome from check-in to departure.'],
        ],
        'airbnb' => [
            ['name' => 'Sofia M.', 'rating' => 5, 'text' => 'The house felt like a private lodge — cozy, light-filled, and every room had character. We did not want to leave.'],
            ['name' => 'David C.', 'rating' => 5, 'text' => 'Immaculate, relaxed vibe, and easy communication. Perfect base for ski days and evenings by the fire.'],
            ['name' => 'Rachel B.', 'rating' => 5, 'text' => 'A gem in the Kootenays. Forest walks nearby and a comfortable, stylish interior.'],
            ['name' => 'Tom W.', 'rating' => 5, 'text' => 'We booked the whole place for a long weekend. Everyone had their own space and the shared areas brought us together.'],
            ['name' => 'Nina F.', 'rating' => 5, 'text' => 'Hospitality was top-tier, and the setting is magical. Already recommending to friends.'],
        ],
    ];
}

/**
 * Pad guest review rows to 5 slots for the admin API (name/text/rating).
 *
 * @param array<int, mixed> $list
 * @return list<array{name: string, text: string, rating: int}>
 */
function btb_guest_reviews_pad_admin_rows(array $list): array {
    $a = $list;
    for ($i = 0; $i < 5; $i++) {
        if (!isset($a[$i]) || !is_array($a[$i])) {
            $a[$i] = ['name' => '', 'text' => '', 'rating' => 5];
        }
        $a[$i]['name'] = (string) ($a[$i]['name'] ?? '');
        $a[$i]['text'] = (string) ($a[$i]['text'] ?? '');
        $a[$i]['rating'] = max(1, min(5, (int) ($a[$i]['rating'] ?? 5)));
    }
    return array_slice($a, 0, 5);
}

/**
 * Guest reviews section as loaded for admin (get_guest_reviews).
 *
 * @return array{section_title: string, section_subtitle: string, vrbo: list<array{name: string, text: string, rating: int}>, airbnb: list<array{name: string, text: string, rating: int}>}
 */
function btb_guest_reviews_admin_api_data($conn): array {
    $def = btb_guest_reviews_default_payload();
    $title = $def['section_title'];
    $sub = $def['section_subtitle'];
    $vr = btb_guest_reviews_pad_admin_rows($def['vrbo']);
    $ar = btb_guest_reviews_pad_admin_rows($def['airbnb']);
    if (!$conn || !btb_db_table_exists($conn, 'guest_reviews_settings')) {
        return [
            'section_title' => $title,
            'section_subtitle' => $sub,
            'vrbo' => $vr,
            'airbnb' => $ar,
        ];
    }
    $row = @$conn->query('SELECT * FROM guest_reviews_settings WHERE id = 1');
    if ($row && $row->num_rows > 0) {
        $d = $row->fetch_assoc();
        if (is_array($d)) {
            if (!empty(trim((string) ($d['section_title'] ?? '')))) {
                $title = (string) $d['section_title'];
            }
            if (array_key_exists('section_subtitle', $d) && $d['section_subtitle'] !== null) {
                $sub = (string) $d['section_subtitle'];
            }
            $jV = json_decode((string) ($d['vrbo_reviews_json'] ?? '[]'), true);
            $jA = json_decode((string) ($d['airbnb_reviews_json'] ?? '[]'), true);
            if (is_array($jV)) {
                $vr = btb_guest_reviews_pad_admin_rows($jV);
            }
            if (is_array($jA)) {
                $ar = btb_guest_reviews_pad_admin_rows($jA);
            }
        }
    }
    return [
        'section_title' => $title,
        'section_subtitle' => $sub,
        'vrbo' => $vr,
        'airbnb' => $ar,
    ];
}

/**
 * Ensure guest_reviews_settings exists and persist admin POST (section_title, JSON blobs).
 *
 * @param array<string,mixed> $post
 * @return array{success: bool, error?: string}
 */
function btb_save_guest_reviews_from_post($conn, array $post): array {
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    $create = "CREATE TABLE IF NOT EXISTS `guest_reviews_settings` (
      `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
      `section_title` VARCHAR(500) NOT NULL DEFAULT 'Guest reviews',
      `section_subtitle` TEXT NULL,
      `vrbo_reviews_json` LONGTEXT NULL,
      `airbnb_reviews_json` LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($create)) {
        return ['success' => false, 'error' => 'Could not create guest_reviews_settings: ' . $conn->error];
    }

    $title = trim((string) ($post['section_title'] ?? 'Guest reviews'));
    if ($title === '') {
        $title = 'Guest reviews';
    }
    $sub = (string) ($post['section_subtitle'] ?? '');
    $jVr = (string) ($post['vrbo_reviews'] ?? '[]');
    $jA = (string) ($post['airbnb_reviews'] ?? '[]');

    json_decode($jVr, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid vrbo_reviews JSON'];
    }
    json_decode($jA, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid airbnb_reviews JSON'];
    }

    $sql = "INSERT INTO `guest_reviews_settings` (`id`, `section_title`, `section_subtitle`, `vrbo_reviews_json`, `airbnb_reviews_json`) VALUES (1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `section_title` = VALUES(`section_title`), `section_subtitle` = VALUES(`section_subtitle`), `vrbo_reviews_json` = VALUES(`vrbo_reviews_json`), `airbnb_reviews_json` = VALUES(`airbnb_reviews_json`)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    $stmt->bind_param('ssss', $title, $sub, $jVr, $jA);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();

        return ['success' => false, 'error' => $err];
    }
    $stmt->close();

    return ['success' => true];
}

/**
 * Merge legacy two-paragraph storage into one block (double newline between non-empty parts).
 */
function btb_booking_success_banner_merge_paragraph_parts(string $p1, string $p2): string {
    $a = trim($p1);
    $b = trim($p2);
    if ($b === '') {
        return $p1;
    }
    if ($a === '') {
        return $p2;
    }
    return $a . "\n\n" . $b;
}

/**
 * If row still has paragraph_2, merge into paragraph_1 and clear paragraph_2 (one-time per DB row).
 */
function btb_booking_success_banner_migrate_two_paragraph_columns($conn): void {
    if (!$conn || !btb_db_table_exists($conn, 'booking_success_banner_settings')) {
        return;
    }
    $res = @$conn->query('SELECT `paragraph_1`, `paragraph_2` FROM `booking_success_banner_settings` WHERE `id` = 1 LIMIT 1');
    if (!$res || $res->num_rows === 0) {
        return;
    }
    $row = $res->fetch_assoc();
    if (!is_array($row)) {
        return;
    }
    $p2 = trim((string) ($row['paragraph_2'] ?? ''));
    if ($p2 === '') {
        return;
    }
    $merged = btb_booking_success_banner_merge_paragraph_parts((string) ($row['paragraph_1'] ?? ''), (string) ($row['paragraph_2'] ?? ''));
    $stmt = $conn->prepare('UPDATE `booking_success_banner_settings` SET `paragraph_1` = ?, `paragraph_2` = \'\' WHERE `id` = 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $merged);
    $stmt->execute();
    $stmt->close();
}

/**
 * Default post-booking success overlay (room pages + massage).
 *
 * @return array{heading: string, paragraph: string, button_label: string, button_url: string}
 */
function btb_booking_success_banner_defaults(): array {
    return [
        'heading' => 'Your booking has been submitted!',
        'paragraph' => "We've sent you a confirmation email. Once your booking is approved, you'll be able to proceed with the payment.\n\nYou can also make changes to your booking in your personal account.",
        'button_label' => 'My Account',
        'button_url' => 'dashboard.html',
    ];
}

/**
 * Green nested box after guest signs in / registers inside the post-booking overlay.
 *
 * @return array{auth_login_message: string, auth_login_close_label: string, auth_login_account_label: string, auth_login_account_url: string}
 */
function btb_booking_success_auth_login_defaults(): array {
    return [
        'auth_login_message' => "Welcome back!\n\nAll your bookings are available in your personal account.\n\nYou can find it in the menu in the top right corner of the site",
        'auth_login_close_label' => 'Close',
        'auth_login_account_label' => 'To my account',
        'auth_login_account_url' => 'dashboard.html',
    ];
}

/**
 * Add auth-success columns to booking_success_banner_settings (idempotent).
 */
function btb_ensure_booking_success_banner_auth_columns(?mysqli $conn): void {
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'booking_success_banner_settings')) {
        return;
    }
    $defs = [
        'auth_login_message' => 'TEXT NULL',
        'auth_login_close_label' => "VARCHAR(255) NOT NULL DEFAULT 'Close'",
        'auth_login_account_label' => "VARCHAR(255) NOT NULL DEFAULT 'To my account'",
        'auth_login_account_url' => "VARCHAR(1024) NOT NULL DEFAULT 'dashboard.html'",
    ];
    foreach ($defs as $col => $ddl) {
        $esc = $conn->real_escape_string($col);
        $chk = @$conn->query("SHOW COLUMNS FROM `booking_success_banner_settings` LIKE '{$esc}'");
        if ($chk && $chk->num_rows > 0) {
            continue;
        }
        $colSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        if ($colSafe === '') {
            continue;
        }
        $sql = "ALTER TABLE `booking_success_banner_settings` ADD COLUMN `{$colSafe}` {$ddl}";
        if (!@$conn->query($sql)) {
            error_log('btb_ensure_booking_success_banner_auth_columns: ALTER failed ' . $colSafe . ': ' . $conn->error);
        }
    }
}

/**
 * Safe href for the success banner CTA (blocks javascript:/data:).
 */
function btb_booking_success_banner_sanitize_url(string $url): string {
    $u = trim($url);
    $def = btb_booking_success_banner_defaults()['button_url'];
    if ($u === '') {
        return $def;
    }
    if (preg_match('/^\s*javascript:/i', $u) || preg_match('/^\s*data:/i', $u)) {
        return $def;
    }
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if ($u[0] === '/' || $u[0] === '.' || preg_match('/^[A-Za-z0-9._\-]+\.html/i', $u)) {
        return $u;
    }
    return $def;
}

/**
 * Ensure booking_success_banner_settings exists and seed id=1.
 */
function btb_ensure_booking_success_banner_settings_table($conn): bool {
    if (!$conn) {
        return false;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `booking_success_banner_settings` (
      `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
      `heading` VARCHAR(500) NOT NULL DEFAULT 'Your booking has been submitted!',
      `paragraph_1` TEXT NULL,
      `paragraph_2` TEXT NULL,
      `button_label` VARCHAR(255) NOT NULL DEFAULT 'My Account',
      `button_url` VARCHAR(1024) NOT NULL DEFAULT 'dashboard.html'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('btb_ensure_booking_success_banner_settings_table: ' . $conn->error);
        return false;
    }
    $d = btb_booking_success_banner_defaults();
    $id = 1;
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO `booking_success_banner_settings` (`id`, `heading`, `paragraph_1`, `paragraph_2`, `button_label`, `button_url`) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('btb_ensure_booking_success_banner_settings_table prepare: ' . $conn->error);
        return false;
    }
    $emptyP2 = '';
    $stmt->bind_param(
        'isssss',
        $id,
        $d['heading'],
        $d['paragraph'],
        $emptyP2,
        $d['button_label'],
        $d['button_url']
    );
    if (!$stmt->execute()) {
        error_log('btb_ensure_booking_success_banner_settings_table insert: ' . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();
    btb_ensure_booking_success_banner_auth_columns($conn);
    return true;
}

/**
 * Banner copy for public API and admin load.
 *
 * @return array{heading: string, paragraph: string, button_label: string, button_url: string, auth_login_message: string, auth_login_close_label: string, auth_login_account_label: string, auth_login_account_url: string}
 */
function btb_booking_success_banner_api_data($conn): array {
    $def = btb_booking_success_banner_defaults();
    $authDef = btb_booking_success_auth_login_defaults();
    if (!$conn) {
        return array_merge($def, $authDef);
    }
    if (!btb_ensure_booking_success_banner_settings_table($conn)) {
        return array_merge($def, $authDef);
    }
    btb_ensure_booking_success_banner_auth_columns($conn);
    btb_booking_success_banner_migrate_two_paragraph_columns($conn);
    $res = @$conn->query(
        'SELECT `heading`, `paragraph_1`, `paragraph_2`, `button_label`, `button_url`, `auth_login_message`, `auth_login_close_label`, `auth_login_account_label`, `auth_login_account_url` FROM `booking_success_banner_settings` WHERE `id` = 1 LIMIT 1'
    );
    if (!$res || $res->num_rows === 0) {
        return array_merge($def, $authDef);
    }
    $row = $res->fetch_assoc();
    if (!is_array($row)) {
        return array_merge($def, $authDef);
    }
    $h = trim((string) ($row['heading'] ?? ''));
    $bl = trim((string) ($row['button_label'] ?? ''));
    $paragraph = btb_booking_success_banner_merge_paragraph_parts(
        (string) ($row['paragraph_1'] ?? ''),
        (string) ($row['paragraph_2'] ?? '')
    );
    $authMsg = trim((string) ($row['auth_login_message'] ?? ''));
    $authClose = trim((string) ($row['auth_login_close_label'] ?? ''));
    $authAcctLbl = trim((string) ($row['auth_login_account_label'] ?? ''));
    return [
        'heading' => $h !== '' ? $h : $def['heading'],
        'paragraph' => $paragraph,
        'button_label' => $bl !== '' ? $bl : $def['button_label'],
        'button_url' => btb_booking_success_banner_sanitize_url((string) ($row['button_url'] ?? '')),
        'auth_login_message' => $authMsg !== '' ? $authMsg : $authDef['auth_login_message'],
        'auth_login_close_label' => $authClose !== '' ? $authClose : $authDef['auth_login_close_label'],
        'auth_login_account_label' => $authAcctLbl !== '' ? $authAcctLbl : $authDef['auth_login_account_label'],
        'auth_login_account_url' => btb_booking_success_banner_sanitize_url((string) ($row['auth_login_account_url'] ?? '')),
    ];
}

/**
 * Persist banner fields from admin POST.
 *
 * @param array<string,mixed> $post
 * @return array{success: bool, error?: string}
 */
function btb_save_booking_success_banner_from_post($conn, array $post): array {
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    if (!btb_ensure_booking_success_banner_settings_table($conn)) {
        return ['success' => false, 'error' => 'Could not ensure booking_success_banner_settings'];
    }
    btb_ensure_booking_success_banner_auth_columns($conn);
    $def = btb_booking_success_banner_defaults();
    $authDef = btb_booking_success_auth_login_defaults();
    $heading = trim((string) ($post['heading'] ?? ''));
    if ($heading === '') {
        $heading = $def['heading'];
    }
    if (function_exists('mb_substr')) {
        $heading = mb_substr($heading, 0, 500, 'UTF-8');
    } else {
        $heading = substr($heading, 0, 500);
    }
    $paragraph = (string) ($post['paragraph'] ?? '');
    if ($paragraph === '' && (isset($post['paragraph_1']) || isset($post['paragraph_2']))) {
        $paragraph = btb_booking_success_banner_merge_paragraph_parts(
            (string) ($post['paragraph_1'] ?? ''),
            (string) ($post['paragraph_2'] ?? '')
        );
    }
    $emptyP2 = '';
    $btnLabel = trim((string) ($post['button_label'] ?? ''));
    if ($btnLabel === '') {
        $btnLabel = $def['button_label'];
    }
    if (function_exists('mb_substr')) {
        $btnLabel = mb_substr($btnLabel, 0, 255, 'UTF-8');
    } else {
        $btnLabel = substr($btnLabel, 0, 255);
    }
    $btnUrl = btb_booking_success_banner_sanitize_url((string) ($post['button_url'] ?? ''));
    if (function_exists('mb_substr')) {
        $btnUrl = mb_substr($btnUrl, 0, 1024, 'UTF-8');
    } else {
        $btnUrl = substr($btnUrl, 0, 1024);
    }

    $authMsg = (string) ($post['auth_login_message'] ?? '');
    $authClose = trim((string) ($post['auth_login_close_label'] ?? ''));
    if ($authClose === '') {
        $authClose = $authDef['auth_login_close_label'];
    }
    if (function_exists('mb_substr')) {
        $authClose = mb_substr($authClose, 0, 255, 'UTF-8');
    } else {
        $authClose = substr($authClose, 0, 255);
    }
    $authAcctLbl = trim((string) ($post['auth_login_account_label'] ?? ''));
    if ($authAcctLbl === '') {
        $authAcctLbl = $authDef['auth_login_account_label'];
    }
    if (function_exists('mb_substr')) {
        $authAcctLbl = mb_substr($authAcctLbl, 0, 255, 'UTF-8');
    } else {
        $authAcctLbl = substr($authAcctLbl, 0, 255);
    }
    $authAcctUrl = btb_booking_success_banner_sanitize_url((string) ($post['auth_login_account_url'] ?? ''));
    if (function_exists('mb_substr')) {
        $authAcctUrl = mb_substr($authAcctUrl, 0, 1024, 'UTF-8');
    } else {
        $authAcctUrl = substr($authAcctUrl, 0, 1024);
    }

    $sql = 'INSERT INTO `booking_success_banner_settings` (`id`, `heading`, `paragraph_1`, `paragraph_2`, `button_label`, `button_url`, `auth_login_message`, `auth_login_close_label`, `auth_login_account_label`, `auth_login_account_url`)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `heading` = VALUES(`heading`), `paragraph_1` = VALUES(`paragraph_1`), `paragraph_2` = VALUES(`paragraph_2`), `button_label` = VALUES(`button_label`), `button_url` = VALUES(`button_url`), `auth_login_message` = VALUES(`auth_login_message`), `auth_login_close_label` = VALUES(`auth_login_close_label`), `auth_login_account_label` = VALUES(`auth_login_account_label`), `auth_login_account_url` = VALUES(`auth_login_account_url`)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    $stmt->bind_param('sssssssss', $heading, $paragraph, $emptyP2, $btnLabel, $btnUrl, $authMsg, $authClose, $authAcctLbl, $authAcctUrl);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();
        return ['success' => false, 'error' => $err];
    }
    $stmt->close();
    return ['success' => true];
}

/**
 * Check-in conditions modal on room booking pages (link under the form).
 *
 * @return array{heading: string, trigger_label: string, paragraph_1: string, paragraph_2: string, about_link_label: string, about_link_url: string}
 */
function btb_checkin_conditions_defaults(): array
{
    return [
        'heading' => 'Check-in conditions',
        'trigger_label' => 'Check-in conditions',
        'paragraph_1' => 'Check-in from 3:00 PM, Check-out until 11:00 AM.',
        'paragraph_2' => '',
        'about_link_label' => '',
        'about_link_url' => '',
    ];
}

function btb_checkin_conditions_sanitize_url(string $url): string
{
    $u = trim($url);
    $def = (string) (btb_checkin_conditions_defaults()['about_link_url'] ?? 'about.php');
    if ($u === '') {
        return $def;
    }
    if (preg_match('/^\s*javascript:/i', $u) || preg_match('/^\s*data:/i', $u)) {
        return $def;
    }
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if ($u[0] === '/' || $u[0] === '.' || preg_match('/^[A-Za-z0-9._\-]+\.php/i', $u) || preg_match('/^[A-Za-z0-9._\-]+\.html/i', $u)) {
        return $u;
    }

    return $def;
}

function btb_ensure_checkin_conditions_settings_table($conn): bool
{
    if (!$conn) {
        return false;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `checkin_conditions_settings` (
      `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
      `heading` VARCHAR(500) NOT NULL DEFAULT 'Check-in conditions',
      `trigger_label` VARCHAR(255) NOT NULL DEFAULT 'Check-in conditions',
      `paragraph_1` TEXT NULL,
      `paragraph_2` TEXT NULL,
      `about_link_label` VARCHAR(255) NOT NULL DEFAULT 'About Us page',
      `about_link_url` VARCHAR(1024) NOT NULL DEFAULT 'about.php',
      `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('btb_ensure_checkin_conditions_settings_table: ' . $conn->error);

        return false;
    }
    $d = btb_checkin_conditions_defaults();
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO `checkin_conditions_settings` (`id`, `heading`, `trigger_label`, `paragraph_1`, `paragraph_2`, `about_link_label`, `about_link_url`)
         VALUES (1, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $h = (string) $d['heading'];
        $t = (string) $d['trigger_label'];
        $p1 = (string) $d['paragraph_1'];
        $p2 = (string) $d['paragraph_2'];
        $ll = (string) $d['about_link_label'];
        $lu = (string) $d['about_link_url'];
        $stmt->bind_param('ssssss', $h, $t, $p1, $p2, $ll, $lu);
        @$stmt->execute();
        $stmt->close();
    }

    return true;
}

/**
 * @return array{heading: string, trigger_label: string, paragraph_1: string, paragraph_2: string, about_link_label: string, about_link_url: string}
 */
function btb_checkin_conditions_api_data($conn): array
{
    $def = btb_checkin_conditions_defaults();
    if (!$conn || !btb_ensure_checkin_conditions_settings_table($conn)) {
        return $def;
    }
    $res = @$conn->query(
        'SELECT `heading`, `trigger_label`, `paragraph_1`, `paragraph_2`, `about_link_label`, `about_link_url`
         FROM `checkin_conditions_settings` WHERE `id` = 1 LIMIT 1'
    );
    if (!$res || $res->num_rows === 0) {
        return $def;
    }
    $row = $res->fetch_assoc();
    if (!is_array($row)) {
        return $def;
    }
    $trim = static function ($v, $fallback) {
        $t = trim((string) ($v ?? ''));

        return $t !== '' ? $t : (string) $fallback;
    };

    return [
        'heading' => $trim($row['heading'] ?? '', $def['heading']),
        'trigger_label' => $trim($row['trigger_label'] ?? '', $def['trigger_label']),
        'paragraph_1' => (string) ($row['paragraph_1'] ?? $def['paragraph_1']),
        'paragraph_2' => (string) ($row['paragraph_2'] ?? $def['paragraph_2']),
        'about_link_label' => $trim($row['about_link_label'] ?? '', $def['about_link_label']),
        'about_link_url' => btb_checkin_conditions_sanitize_url((string) ($row['about_link_url'] ?? '')),
    ];
}

/**
 * Modal body HTML (escaped text + optional About link).
 */
function btb_checkin_conditions_body_html(array $data): string
{
    $p1 = trim((string) ($data['paragraph_1'] ?? ''));
    if ($p1 === '') {
        return '';
    }

    return '<p>' . nl2br(htmlspecialchars($p1, ENT_QUOTES, 'UTF-8')) . '</p>';
}

/**
 * @param array<string,mixed> $post
 *
 * @return array{success: bool, error?: string}
 */
function btb_save_checkin_conditions_from_post($conn, array $post): array
{
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    if (!btb_ensure_checkin_conditions_settings_table($conn)) {
        return ['success' => false, 'error' => 'Could not ensure checkin_conditions_settings'];
    }
    $def = btb_checkin_conditions_defaults();
    $trim500 = static function ($s, $fallback) {
        $t = trim((string) $s);
        if ($t === '') {
            return (string) $fallback;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, 500, 'UTF-8');
        }

        return substr($t, 0, 500);
    };
    $trim255 = static function ($s, $fallback) {
        $t = trim((string) $s);
        if ($t === '') {
            return (string) $fallback;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, 255, 'UTF-8');
        }

        return substr($t, 0, 255);
    };

    $heading = $trim500($post['heading'] ?? '', $def['heading']);
    $trigger = $trim255($post['trigger_label'] ?? '', $def['trigger_label']);
    $p1 = trim((string) ($post['paragraph_1'] ?? $def['paragraph_1']));
    $p2 = '';
    $linkLabel = '';
    $linkUrl = '';

    $sql = 'INSERT INTO `checkin_conditions_settings` (`id`, `heading`, `trigger_label`, `paragraph_1`, `paragraph_2`, `about_link_label`, `about_link_url`)
            VALUES (1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `heading` = VALUES(`heading`),
                `trigger_label` = VALUES(`trigger_label`),
                `paragraph_1` = VALUES(`paragraph_1`),
                `paragraph_2` = VALUES(`paragraph_2`),
                `about_link_label` = VALUES(`about_link_label`),
                `about_link_url` = VALUES(`about_link_url`)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    $stmt->bind_param('ssssss', $heading, $trigger, $p1, $p2, $linkLabel, $linkUrl);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();

        return ['success' => false, 'error' => $err];
    }
    $stmt->close();

    return ['success' => true];
}

/**
 * Default internal scenario descriptions for the admin UI only (not emailed to guests).
 *
 * @return array<string, string>
 */
function btb_email_template_scenario_notes_defaults(): array
{
    return [
        'booking_confirmation_guest' => 'Room flow — step 1 (guest): sent immediately after someone submits a room booking request on the website. Confirms you received their request, that you are reviewing it, and that you will email them again once it is approved or declined. Includes their reference code and requested stay details.',
        'booking_request_host' => 'Room flow — step 1 (host): sent to your notification inbox when a guest submits a room booking request so staff can check availability and approve or decline it in admin.',
        'guest_bookings_digest_guest' => 'Guest summary: one email after every room + wellness request for that email is no longer pending (approved, declined/cancelled, or removed). Lists approved and not-approved lines, estimated combined total with taxes like My Bookings, and a single link to My Bookings to pay online when Stripe is configured.',
        'guest_payment_succeeded_guest' => 'Guest: after Stripe payment (room or wellness). No How to Find Us / View booking footer row; blue My Bookings CTA + footer photo.',
        'booking_confirmed_host' => 'Room flow — step 2 (host): sent to your notification inbox when staff approve a room booking (guest receives guest_bookings_digest_guest when nothing is left pending for their email).',
        'booking_cancelled_host' => 'Room flow (host): sent when a room booking is cancelled or declined in admin so your inbox matches the guest notification.',
        'massage_booking_guest' => 'Wellness flow — step 1 (guest): sent when a guest submits a wellness booking request (massage, sauna, etc.). Acknowledges receipt while staff review and confirm the appointment.',
        'massage_booking_host' => 'Wellness flow — step 1 (host): sent to your notification inbox when a guest submits a wellness request, with contact details and preferred date/time.',
        'room_booking_updated_guest' => 'Room flow: sent to the guest after they edit an existing room booking request from My Bookings (dates, room, guest count, or pets).',
        'room_booking_updated_host' => 'Room flow: sent to your notification inbox when a guest saves changes to their room booking request from the website.',
        'massage_booking_updated_guest' => 'Wellness flow: sent to the guest after they edit an existing wellness booking request from My Bookings (service, date, or time).',
        'massage_booking_updated_host' => 'Wellness flow: sent to your notification inbox when a guest saves changes to a wellness booking request from the website.',
        'massage_booking_confirmed_host' => 'Wellness flow — staff confirmation (host): sent when staff confirm a wellness booking (guest receives guest_bookings_digest_guest when nothing is left pending for their email).',
        'massage_booking_cancelled_host' => 'Wellness flow (host): sent when staff cancel a wellness booking so your inbox reflects the cancellation.',
        'massage_payment_succeeded_host' => 'Wellness flow — payment (host): sent after wellness-only Stripe checkout, or combined checkout with wellness rows only (one email listing all paid lines). If the combined charge includes any room stay, the room host payment template is used instead.',
        'user_register_welcome' => 'Account: sent right after a guest successfully creates an account.',
        'booking_payment_succeeded_host' => "Room flow — payment (host): sent after room-only Stripe checkout, or after combined checkout that includes at least one room (one email listing every paid line for that charge, including wellness).",
        'host_chat_guest_message_host' => 'My Account chat (host): guest sent a message. No thread subject — short heading, body, and admin CTA. Placeholder {{chat.heading_line}}.',
        'guest_chat_staff_reply_guest' => 'My Account chat (guest): staff reply from admin. “From …” text: BTB_CHAT_STAFF_FROM_LABEL or {{chat.staff_from}}. CTA button: {{cta_label}} → {{cta_url}} (placeholder {{messages_url}}).',
    ];
}

/**
 * Defaults for editable outbound email templates (guest + host).
 *
 * @return array<int, array<string, string>>
 */
function btb_email_template_defaults(): array
{
    $scenario = btb_email_template_scenario_notes_defaults();
    $rows = [
        [
            'template_key' => 'booking_confirmation_guest',
            'display_name' => 'Room — Guest: booking request received (pending approval)',
            'audience' => 'guest',
            'subject' => 'Booking Confirmation - Back to Base Hotel',
            'heading' => 'Booking Confirmation',
            'body' => "Dear {{booking.guest_name}},\n\nThank you for your booking! Your reservation has been received.\n\nWe are reviewing your request and will email you again once it is approved or declined.",
            'body_after' => "We will review availability and send you a confirmation email shortly.",
            'cta_label' => 'View Request',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'booking_request_host',
            'display_name' => 'Room — Host: new booking request',
            'audience' => 'host',
            'subject' => 'New Booking Request - Back to Base Hotel',
            'heading' => 'New Booking Request',
            'body' => "A new room booking request was submitted through the website.",
            'body_after' => "Review the guest and stay details below, then open admin to approve or decline the request.",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'guest_bookings_digest_guest',
            'display_name' => 'Guest: summary when all requests are resolved (room + wellness)',
            'audience' => 'guest',
            'subject' => 'Your booking requests — summary — Back to Base Hotel',
            'heading' => 'Your booking requests — summary',
            'body' => "Here is a summary of your requests now that our team has reviewed everything on file for your email address.",
            'body_after' => '',
            'cta_label' => '',
            'cta_url' => '',
            'image_url' => '',
        ],
        [
            'template_key' => 'guest_payment_succeeded_guest',
            'display_name' => 'Guest: payment received (room or wellness)',
            'audience' => 'guest',
            'subject' => 'Payment received — Back to Base',
            'heading' => 'Payment received',
            'body' => "Dear {{booking.guest_name}},\n\nThank you — your payment was received successfully.",
            'body_after' => "Details for the paid booking are shown below. We look forward to welcoming you.",
            'cta_label' => 'My Bookings',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'booking_confirmed_host',
            'display_name' => 'Room — Host: booking approved in admin',
            'audience' => 'host',
            'subject' => 'Room booking approved (guest notified) — Back to Base',
            'heading' => 'Room booking approved',
            'body' => "You approved a room booking in admin. The guest receives guest_bookings_digest_guest when no requests remain pending for their email.",
            'body_after' => "The summary below matches what the guest sees. Open admin if you need to follow up.",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'booking_cancelled_host',
            'display_name' => 'Room — Host: booking cancelled in admin',
            'audience' => 'host',
            'subject' => 'Room booking cancelled — Back to Base',
            'heading' => 'Room booking cancelled',
            'body' => "A room booking was cancelled or declined in admin. The guest receives guest_bookings_digest_guest when no requests remain pending for their email.",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_guest',
            'display_name' => 'Wellness — Guest: booking request received (pending confirmation)',
            'audience' => 'guest',
            'subject' => 'We received your wellness booking request — Back to Base',
            'heading' => 'Wellness booking request received',
            'body' => "Dear {{booking.guest_name}},\n\nThank you — we have received your wellness booking request and will confirm your appointment as soon as possible.",
            'body_after' => "We will review availability and send you a confirmation email shortly.",
            'cta_label' => 'View booking',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_host',
            'display_name' => 'Wellness — Host: new booking request',
            'audience' => 'host',
            'subject' => 'New wellness booking (massage / sauna) — Back to Base',
            'heading' => 'New wellness booking request',
            'body' => "A new wellness booking request was submitted through the website.",
            'body_after' => "Contact the guest if you need more information before confirming.",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'room_booking_updated_guest',
            'display_name' => 'Room — Guest: booking request updated',
            'audience' => 'guest',
            'subject' => 'Your booking was updated — Back to Base',
            'heading' => 'Booking was updated',
            'body' => "Your room booking was updated from My Bookings. The new details are shown below.",
            'body_after' => "If anything looks wrong, open your booking or contact us.",
            'cta_label' => 'View Request',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'room_booking_updated_host',
            'display_name' => 'Room — Host: guest updated booking request',
            'audience' => 'host',
            'subject' => 'Guest updated a room booking — Back to Base',
            'heading' => 'Guest updated a room booking',
            'body' => "A guest saved room booking changes from the website (My Bookings).",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_updated_guest',
            'display_name' => 'Wellness — Guest: booking request updated',
            'audience' => 'guest',
            'subject' => 'Your wellness booking was updated — Back to Base',
            'heading' => 'Wellness booking updated',
            'body' => "Your wellness booking was updated from My Bookings. The new details are below.",
            'body_after' => "Reply to this email if you need help choosing another time.",
            'cta_label' => 'View Request',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_updated_host',
            'display_name' => 'Wellness — Host: guest updated booking request',
            'audience' => 'host',
            'subject' => 'Guest updated a wellness booking — Back to Base',
            'heading' => 'Guest updated a wellness booking',
            'body' => "A guest saved wellness booking changes from the website (My Bookings).",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_confirmed_host',
            'display_name' => 'Wellness — Host: appointment confirmed in admin',
            'audience' => 'host',
            'subject' => 'Wellness booking confirmed (guest notified) — Back to Base',
            'heading' => 'Wellness booking confirmed',
            'body' => "A wellness booking was confirmed in admin. The guest receives guest_bookings_digest_guest when no requests remain pending for their email.",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_booking_cancelled_host',
            'display_name' => 'Wellness — Host: booking cancelled in admin',
            'audience' => 'host',
            'subject' => 'Wellness booking cancelled (guest notified) — Back to Base',
            'heading' => 'Wellness booking cancelled',
            'body' => "A wellness booking was cancelled in admin. The guest receives guest_bookings_digest_guest when no requests remain pending for their email.",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'user_register_welcome',
            'display_name' => 'Account — Guest: welcome after registration',
            'audience' => 'guest',
            'subject' => 'Welcome to Back to Base',
            'heading' => 'Welcome to Back to Base',
            'body' => "Your account was created successfully.\n\nYou can now manage bookings and messages in your account.",
            'body_after' => "If you did not create this account, contact us right away.",
            'cta_label' => 'Open account',
            'cta_url' => '{{dashboard_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'booking_payment_succeeded_host',
            'display_name' => 'Room — Host: guest paid — stay confirmed',
            'audience' => 'host',
            'subject' => 'Booking payment received — Back to Base',
            'heading' => 'Booking payment received',
            'body' => "Payment was received for a room booking. The reservation is now fully confirmed on the guest side.",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'massage_payment_succeeded_host',
            'display_name' => 'Wellness — Host: guest paid online',
            'audience' => 'host',
            'subject' => 'Wellness booking payment received — Back to Base',
            'heading' => 'Wellness payment received',
            'body' => "Payment was received for a wellness booking.",
            'body_after' => "",
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'host_chat_guest_message_host',
            'display_name' => 'Chat — Host: new message from guest',
            'audience' => 'host',
            'subject' => 'New chat message',
            'heading' => '{{chat.heading_line}}',
            'body' => '',
            'body_after' => '',
            'cta_label' => 'Open admin panel',
            'cta_url' => '{{admin_url}}',
            'image_url' => '',
        ],
        [
            'template_key' => 'guest_chat_staff_reply_guest',
            'display_name' => 'Chat — Guest: reply from Back to Base',
            'audience' => 'guest',
            'subject' => 'New message — Back to Base',
            'heading' => '{{chat.heading_line}}',
            'body' => '',
            'body_after' => '',
            'cta_label' => 'Chat with Rob',
            'cta_url' => '{{messages_url}}',
            'image_url' => '',
        ],
    ];
    foreach ($rows as $i => $row) {
        $k = (string) ($row['template_key'] ?? '');
        $rows[$i]['scenario_notes'] = (string) ($scenario[$k] ?? '');
    }

    return $rows;
}

/** Ensure scenario_notes column exists on legacy installs. */
function btb_ensure_email_template_scenario_notes_column($conn): void
{
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'email_template_settings')) {
        return;
    }
    $chk = @$conn->query("SHOW COLUMNS FROM `email_template_settings` LIKE 'scenario_notes'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    if (!@$conn->query("ALTER TABLE `email_template_settings` ADD COLUMN `scenario_notes` MEDIUMTEXT NULL AFTER `display_name`")) {
        error_log('btb_ensure_email_template_scenario_notes_column: ' . $conn->error);
    }
}

/** Second body block (sign-off) shown after the structured summary in outbound mail. */
function btb_ensure_email_template_body_after_column($conn): void
{
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'email_template_settings')) {
        return;
    }
    $chk = @$conn->query("SHOW COLUMNS FROM `email_template_settings` LIKE 'body_after'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    if (!@$conn->query("ALTER TABLE `email_template_settings` ADD COLUMN `body_after` MEDIUMTEXT NULL AFTER `body`")) {
        error_log('btb_ensure_email_template_body_after_column: ' . $conn->error);
    }
}

/** Third body block (contact info), right-aligned, after body_after in outbound mail. */
function btb_ensure_email_template_body_contact_column($conn): void
{
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'email_template_settings')) {
        return;
    }
    $chk = @$conn->query("SHOW COLUMNS FROM `email_template_settings` LIKE 'body_contact'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    if (!@$conn->query("ALTER TABLE `email_template_settings` ADD COLUMN `body_contact` MEDIUMTEXT NULL AFTER `body_after`")) {
        error_log('btb_ensure_email_template_body_contact_column: ' . $conn->error);
    }
}

function btb_ensure_email_templates_settings_table($conn): bool
{
    if (!$conn) {
        return false;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `email_template_settings` (
      `template_key` VARCHAR(80) NOT NULL PRIMARY KEY,
      `display_name` VARCHAR(255) NOT NULL DEFAULT '',
      `scenario_notes` MEDIUMTEXT NULL,
      `audience` VARCHAR(20) NOT NULL DEFAULT 'guest',
      `subject` VARCHAR(255) NOT NULL DEFAULT '',
      `heading` VARCHAR(500) NOT NULL DEFAULT '',
      `body` MEDIUMTEXT NULL,
      `body_after` MEDIUMTEXT NULL,
      `body_contact` MEDIUMTEXT NULL,
      `cta_label` VARCHAR(255) NOT NULL DEFAULT '',
      `cta_url` VARCHAR(1024) NOT NULL DEFAULT '',
      `image_url` VARCHAR(1024) NOT NULL DEFAULT '',
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('btb_ensure_email_templates_settings_table: ' . $conn->error);
        return false;
    }
    btb_ensure_email_template_scenario_notes_column($conn);
    btb_ensure_email_template_body_after_column($conn);
    btb_ensure_email_template_body_contact_column($conn);
    $defaults = btb_email_template_defaults();
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO `email_template_settings`
        (`template_key`, `display_name`, `scenario_notes`, `audience`, `subject`, `heading`, `body`, `body_after`, `body_contact`, `cta_label`, `cta_url`, `image_url`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('btb_ensure_email_templates_settings_table prepare: ' . $conn->error);
        return false;
    }
    foreach ($defaults as $row) {
        $k = (string) ($row['template_key'] ?? '');
        $dn = (string) ($row['display_name'] ?? '');
        $au = (string) ($row['audience'] ?? 'guest');
        $su = (string) ($row['subject'] ?? '');
        $he = (string) ($row['heading'] ?? '');
        $sn = (string) ($row['scenario_notes'] ?? '');
        $bo = (string) ($row['body'] ?? '');
        $ba = (string) ($row['body_after'] ?? '');
        $bc = (string) ($row['body_contact'] ?? '');
        $cl = (string) ($row['cta_label'] ?? '');
        $cu = (string) ($row['cta_url'] ?? '');
        $iu = (string) ($row['image_url'] ?? '');
        $stmt->bind_param('ssssssssssss', $k, $dn, $sn, $au, $su, $he, $bo, $ba, $bc, $cl, $cu, $iu);
        if (!$stmt->execute()) {
            error_log('btb_ensure_email_templates_settings_table insert: ' . $stmt->error);
        }
    }
    $stmt->close();
    if (function_exists('btb_email_template_fill_empty_admin_meta_from_defaults')) {
        btb_email_template_fill_empty_admin_meta_from_defaults($conn);
    }
    if (function_exists('btb_email_template_delete_duplicate_db_rows')) {
        btb_email_template_delete_duplicate_db_rows($conn);
    }
    if (function_exists('btb_email_template_normalize_trim_duplicate_keys')) {
        btb_email_template_normalize_trim_duplicate_keys($conn);
    }
    return true;
}

/**
 * Seed internal admin labels when missing only — never overwrite staff edits.
 */
function btb_email_template_fill_empty_admin_meta_from_defaults($conn): void
{
    if (!$conn) {
        return;
    }
    $defaults = btb_email_template_defaults();
    $stmtDn = $conn->prepare(
        'UPDATE `email_template_settings` SET `display_name` = ? WHERE `template_key` = ? AND (COALESCE(TRIM(`display_name`), \'\') = \'\')'
    );
    $stmtSn = $conn->prepare(
        'UPDATE `email_template_settings` SET `scenario_notes` = ? WHERE `template_key` = ? AND (`scenario_notes` IS NULL OR COALESCE(TRIM(`scenario_notes`), \'\') = \'\')'
    );
    if (!$stmtDn || !$stmtSn) {
        return;
    }
    foreach ($defaults as $row) {
        $k = (string) ($row['template_key'] ?? '');
        $dn = (string) ($row['display_name'] ?? '');
        $sn = (string) ($row['scenario_notes'] ?? '');
        if ($k !== '' && $dn !== '') {
            $stmtDn->bind_param('ss', $dn, $k);
            @$stmtDn->execute();
        }
        if ($k !== '' && $sn !== '') {
            $stmtSn->bind_param('ss', $sn, $k);
            @$stmtSn->execute();
        }
    }
    $stmtDn->close();
    $stmtSn->close();
}

/** @return array{footer_image_url: string, footer_image_alt: string, outer_background: string, card_background: string} */
function btb_email_branding_defaults(): array
{
    return [
        'footer_image_url' => '',
        'footer_image_alt' => 'Back to Base',
        'outer_background' => '#ffffff',
        'card_background' => '#ffffff',
    ];
}

function btb_email_sanitize_hex_color($value, string $default): string
{
    $s = trim((string) $value);
    if ($s === '') {
        return $default;
    }
    if (preg_match('/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $s, $m)) {
        return '#' . strtolower($m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]);
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $s)) {
        return strtolower($s);
    }

    return $default;
}

/**
 * Turn relative paths (e.g. assets/ from upload_image.php) into absolute https URLs for email clients.
 */
function btb_email_public_http_url(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if (strpos($u, '//') === 0) {
        return 'https:' . $u;
    }
    $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    if ($base === '') {
        return $u;
    }
    if (isset($u[0]) && $u[0] === '/') {
        return $base . $u;
    }

    return $base . '/' . ltrim($u, '/');
}

/** Safe http(s) URL for <img src> / links: blocks javascript/data, makes host-relative URLs absolute. */
function btb_email_safe_public_url(string $url): string
{
    $u = trim($url);
    if ($u === '' || preg_match('/^\s*javascript:/i', $u) || preg_match('/^\s*data:/i', $u)) {
        return '';
    }

    return btb_email_public_http_url($u);
}

function btb_ensure_email_branding_settings_table($conn): bool
{
    if (!$conn) {
        return false;
    }
    $sql = 'CREATE TABLE IF NOT EXISTS `email_branding_settings` (
        `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        `footer_image_url` VARCHAR(1024) NOT NULL DEFAULT \'\',
        `footer_image_alt` VARCHAR(255) NOT NULL DEFAULT \'Back to Base\',
        `outer_background` VARCHAR(32) NOT NULL DEFAULT \'#ffffff\',
        `card_background` VARCHAR(32) NOT NULL DEFAULT \'#ffffff\',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!@$conn->query($sql)) {
        error_log('btb_ensure_email_branding_settings_table: ' . $conn->error);

        return false;
    }
    $d = btb_email_branding_defaults();
    $stmt = @$conn->prepare(
        'INSERT IGNORE INTO `email_branding_settings` (`id`, `footer_image_url`, `footer_image_alt`, `outer_background`, `card_background`)
         VALUES (1, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $fu = (string) $d['footer_image_url'];
        $fa = (string) $d['footer_image_alt'];
        $ob = (string) $d['outer_background'];
        $cb = (string) $d['card_background'];
        $stmt->bind_param('ssss', $fu, $fa, $ob, $cb);
        if (!$stmt->execute()) {
            error_log('btb_ensure_email_branding_settings_table seed: ' . $stmt->error);
        }
        $stmt->close();
    }

    // One-time alignment: legacy grey outer background showed a seam vs white footer art.
    @$conn->query("UPDATE `email_branding_settings` SET `outer_background` = '#ffffff' WHERE `id` = 1 AND LOWER(TRIM(`outer_background`)) = '#f4f4f5'");

    return true;
}

function btb_email_branding_api_data($conn): array
{
    $d = btb_email_branding_defaults();
    if (!$conn || !btb_ensure_email_branding_settings_table($conn)) {
        return $d;
    }
    $res = @$conn->query(
        'SELECT `footer_image_url`, `footer_image_alt`, `outer_background`, `card_background` FROM `email_branding_settings` WHERE `id` = 1 LIMIT 1'
    );
    if (!$res || !($row = $res->fetch_assoc()) || !is_array($row)) {
        return $d;
    }

    return [
        'footer_image_url' => trim((string) ($row['footer_image_url'] ?? '')),
        'footer_image_alt' => trim((string) ($row['footer_image_alt'] ?? $d['footer_image_alt'])),
        'outer_background' => btb_email_sanitize_hex_color($row['outer_background'] ?? '', $d['outer_background']),
        'card_background' => btb_email_sanitize_hex_color($row['card_background'] ?? '', $d['card_background']),
    ];
}

function btb_save_email_branding_from_post($conn, array $post): array
{
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    if (!btb_ensure_email_branding_settings_table($conn)) {
        return ['success' => false, 'error' => 'Could not ensure email_branding_settings'];
    }
    $d = btb_email_branding_defaults();
    $fu = trim((string) ($post['footer_image_url'] ?? ''));
    if ($fu !== '') {
        $norm = btb_email_safe_public_url($fu);
        if ($norm !== '') {
            $fu = $norm;
        }
    }
    $fa = trim((string) ($post['footer_image_alt'] ?? ''));
    $ob = btb_email_sanitize_hex_color($post['outer_background'] ?? '', $d['outer_background']);
    $cb = btb_email_sanitize_hex_color($post['card_background'] ?? '', $d['card_background']);
    if ($fa === '') {
        $fa = (string) $d['footer_image_alt'];
    }
    $stmt = $conn->prepare(
        'UPDATE `email_branding_settings` SET `footer_image_url` = ?, `footer_image_alt` = ?, `outer_background` = ?, `card_background` = ? WHERE `id` = 1'
    );
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error ?: 'Prepare failed'];
    }
    $stmt->bind_param('ssss', $fu, $fa, $ob, $cb);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();

        return ['success' => false, 'error' => $err];
    }
    $stmt->close();

    return ['success' => true];
}

function btb_email_ensure_viewport_and_charset(string $html): string
{
    $h = $html;
    if (stripos($h, '<html') === false && stripos($h, '<head') === false) {
        return $h;
    }
    if (stripos($h, 'charset') === false && preg_match('/<head[^>]*>/i', $h, $m)) {
        $h = preg_replace('/<head[^>]*>/i', $m[0] . "\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">", $h, 1);
    }
    if (stripos($h, 'name="viewport"') === false && stripos($h, "name='viewport'") === false && preg_match('/<head[^>]*>/i', $h, $m)) {
        $h = preg_replace('/<head[^>]*>/i', $m[0] . "\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">", $h, 1);
    }
    if (stripos($h, 'name="color-scheme"') === false && stripos($h, "name='color-scheme'") === false && preg_match('/<head[^>]*>/i', $h, $m)) {
        $h = preg_replace(
            '/<head[^>]*>/i',
            $m[0] . "\n<meta name=\"color-scheme\" content=\"light\">\n<meta name=\"supported-color-schemes\" content=\"light\">",
            $h,
            1
        );
    }

    return $h;
}

/**
 * Property contacts as HTML for the footer hero overlay (white text on dark gradient).
 */
function btb_email_property_contact_footer_overlay_html(): string
{
    $addrRaw = defined('BTB_EMAIL_PROPERTY_ADDRESS') ? trim((string) BTB_EMAIL_PROPERTY_ADDRESS) : '';
    $phoneRaw = defined('BTB_EMAIL_PROPERTY_PHONE') ? trim((string) BTB_EMAIL_PROPERTY_PHONE) : '+1 (250)-691-1118';
    $emailRaw = defined('BTB_EMAIL_PROPERTY_EMAIL') ? trim((string) BTB_EMAIL_PROPERTY_EMAIL) : 'backtobasewellness@gmail.com';

    $addr = htmlspecialchars($addrRaw, ENT_QUOTES, 'UTF-8');
    $phoneEsc = htmlspecialchars($phoneRaw, ENT_QUOTES, 'UTF-8');
    $emailEsc = htmlspecialchars($emailRaw, ENT_QUOTES, 'UTF-8');
    $telDial = preg_replace('/[^0-9+]/', '', $phoneRaw);
    $telHref = $telDial !== '' ? htmlspecialchars('tel:' . $telDial, ENT_QUOTES, 'UTF-8') : '';
    $mailto = htmlspecialchars('mailto:' . $emailRaw, ENT_QUOTES, 'UTF-8');

    $aStyle = 'color:#ffffff !important;text-decoration:none;-webkit-text-fill-color:#ffffff !important;';
    $phoneInner = $telHref !== ''
        ? '<a href="' . $telHref . '" style="' . $aStyle . '">' . $phoneEsc . '</a>'
        : '<span style="color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;">' . $phoneEsc . '</span>';
    $emailInner = '<a href="' . $mailto . '" style="' . $aStyle . '">' . $emailEsc . '</a>';

    $inner = '<div class="btb-email-footer-hero-contacts" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.32;color:#ffffff !important;text-align:left;text-shadow:0 1px 3px rgba(0,0,0,0.45);-webkit-text-fill-color:#ffffff !important;">';
    if ($addrRaw !== '') {
        $inner .= '<div style="margin:0 0 5px;font-weight:500;color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;">' . $addr . '</div>';
    }
    $inner .= '<div style="margin:0 0 3px;font-weight:500;color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;">Phone: ' . $phoneInner . '</div>'
        . '<div style="margin:0;font-weight:500;color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;">Email: ' . $emailInner . '</div>'
        . '</div>';

    return $inner;
}

/**
 * Footer hero photo only (no contact overlay on the image).
 *
 * @param string $safeImageUrl Absolute https URL from {@see btb_email_safe_public_url()}
 */
function btb_email_footer_hero_overlay_block_html(string $safeImageUrl, string $altPlain): string
{
    $url = trim($safeImageUrl);
    if ($url === '') {
        return '';
    }
    $srcAttr = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars(trim($altPlain) !== '' ? trim($altPlain) : 'Back to Base', ENT_QUOTES, 'UTF-8');

    return '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;border-collapse:collapse;">'
        . '<tr><td align="center" style="padding:0;line-height:0;font-size:0;">'
        . '<img src="' . $srcAttr . '" alt="' . $alt . '" width="640" style="display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" />'
        . '</td></tr></table>';
}

/**
 * Standalone footer block for legacy/simple HTML (injected before </body>).
 *
 * @param array $br Row from btb_email_branding_api_data()
 */
function btb_email_build_footer_table_html(array $br): string
{
    $urlDb = trim((string) ($br['footer_image_url'] ?? ''));
    $fallback = defined('BTB_EMAIL_DEFAULT_FOOTER_IMAGE_URL') ? trim((string) BTB_EMAIL_DEFAULT_FOOTER_IMAGE_URL) : '';
    $url = btb_email_safe_public_url($urlDb !== '' ? $urlDb : $fallback);
    if ($url === '') {
        return '';
    }
    $altPlain = trim((string) ($br['footer_image_alt'] ?? ''));
    $heroInner = btb_email_footer_hero_overlay_block_html($url, $altPlain !== '' ? $altPlain : 'Back to Base');
    $outerHex = btb_email_sanitize_hex_color($br['outer_background'] ?? '', '#ffffff');
    $cardHex = btb_email_sanitize_hex_color($br['card_background'] ?? '', '#ffffff');
    $outer = htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8');
    $card = htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8');

    return '<!--btb-email-footer-start-->'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;background:' . $outer . ';margin:0;">'
        . '<tr><td align="center" style="padding:8px 12px 24px 12px;">'
        . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;max-width:640px;width:100%;background:' . $card . ';border-radius:0 0 12px 12px;overflow:hidden;border:1px solid #e5e7eb;border-top:0;">'
        . '<tr><td align="center" width="100%" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="padding:0;line-height:0;font-size:0;background:' . $card . ';text-align:center;">'
        . $heroInner
        . '</td></tr></table>'
        . '</td></tr></table>'
        . '<!--btb-email-footer-end-->';
}

function btb_email_wrap_fragment_with_footer(string $fragment, array $br, string $footerBlock): string
{
    $outerHex = btb_email_sanitize_hex_color($br['outer_background'] ?? '', '#ffffff');
    $cardHex = btb_email_sanitize_hex_color($br['card_background'] ?? '', '#ffffff');
    $outer = htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8');
    $card = htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"></head>'
        . '<body bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="margin:0;padding:0;background:' . $outer . ' !important;color:#111827;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($outerHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;background:' . $outer . ';">'
        . '<tr><td align="center" style="padding:16px 12px;">'
        . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" bgcolor="' . htmlspecialchars($cardHex, ENT_QUOTES, 'UTF-8') . '" style="border-collapse:collapse;max-width:640px;width:100%;background:' . $card . ';border-radius:14px;border:1px solid #e5e7eb;overflow:hidden;">'
        . '<tr><td style="padding:22px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.55;color:#111827;">' . $fragment . '</td></tr></table>'
        . $footerBlock
        . '</td></tr></table>'
        . '</body></html>';
}

/**
 * Viewport + optional global footer image for emails without the v2 admin shell.
 *
 * @param string|null $templateKey When key ends with `_host`, skip appending the global photo footer (host mail uses v2 layout or plain fallback without banner).
 */
function btb_email_finalize_outbound_html(string $html, $conn, ?string $templateKey = null): string
{
    $marker = '<!--btb-email-shell-v2-->';
    $html2 = btb_email_ensure_viewport_and_charset($html);
    $br = btb_email_branding_api_data($conn);
    $html2 = preg_replace('/<!--btb-email-footer-start-->[\s\S]*?<!--btb-email-footer-end-->/', '', $html2);
    $tk = trim((string) ($templateKey ?? ''));
    if ($tk !== '' && substr($tk, -5) === '_host') {
        return $html2;
    }
    $footerBlock = btb_email_build_footer_table_html($br);
    if (stripos($html2, $marker) !== false) {
        return $html2;
    }
    if ($footerBlock === '') {
        return $html2;
    }
    if (preg_match('/<\/body>/i', $html2)) {
        return (string) preg_replace('/<\/body>/i', $footerBlock . '</body>', $html2, 1);
    }

    return btb_email_wrap_fragment_with_footer($html2, $br, $footerBlock);
}

/**
 * Template keys accepted by Admin → Emails save API (must match outbound keys you can edit).
 *
 * @return array<string, true>
 */
function btb_email_template_known_keys_map(): array
{
    $out = [];
    foreach (btb_email_template_defaults() as $row) {
        $k = trim((string) ($row['template_key'] ?? ''));
        if ($k !== '') {
            $out[$k] = true;
        }
    }

    return $out;
}

/**
 * Canonical identity for template_key: trim + strip zero-width / BOM / NBSP (legacy duplicates differ only by these).
 */
function btb_email_template_normalize_identity_key(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }
    $stripped = preg_replace('/[\x{200B}-\x{200D}\x{200E}\x{200F}\x{FEFF}\x{00A0}]/u', '', $key);

    return trim((string) $stripped);
}

/**
 * When the DB still has legacy duplicate rows (same template_key) or orphan keys, keep the richest row per key.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function btb_email_template_dedupe_rows_by_template_key(array $rows): array
{
    $best = [];
    $score = static function (array $r): int {
        return strlen((string) ($r['subject'] ?? ''))
            + strlen((string) ($r['body'] ?? ''))
            + strlen((string) ($r['body_after'] ?? ''))
            + strlen((string) ($r['body_contact'] ?? ''))
            + strlen((string) ($r['heading'] ?? ''));
    };
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $k = btb_email_template_normalize_identity_key((string) ($r['template_key'] ?? ''));
        if ($k === '') {
            continue;
        }
        $r['template_key'] = $k;
        if (!isset($best[$k]) || $score($r) > $score($best[$k])) {
            $best[$k] = $r;
        }
    }

    return array_values($best);
}

/**
 * Remove duplicate rows per template_key when the table has an `id` column (legacy schema).
 */
function btb_email_template_delete_duplicate_db_rows($conn): void
{
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'email_template_settings')) {
        return;
    }
    $idCol = @$conn->query("SHOW COLUMNS FROM `email_template_settings` LIKE 'id'");
    if (!$idCol || $idCol->num_rows === 0) {
        return;
    }
    $dup = @$conn->query('SELECT `template_key`, COUNT(*) AS `c` FROM `email_template_settings` GROUP BY `template_key` HAVING `c` > 1');
    if (!$dup) {
        return;
    }
    while ($row = $dup->fetch_assoc()) {
        $k = trim((string) ($row['template_key'] ?? ''));
        if ($k === '') {
            continue;
        }
        $stmt = $conn->prepare(
            'SELECT `id` FROM `email_template_settings` WHERE `template_key` = ? ORDER BY `updated_at` DESC, `id` ASC'
        );
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $k);
        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }
        $resIds = $stmt->get_result();
        $ids = [];
        if ($resIds) {
            while ($idr = $resIds->fetch_assoc()) {
                if (isset($idr['id'])) {
                    $ids[] = (int) $idr['id'];
                }
            }
        }
        $stmt->close();
        if (count($ids) <= 1) {
            continue;
        }
        $keep = array_shift($ids);
        $delStmt = $conn->prepare('DELETE FROM `email_template_settings` WHERE `id` = ?');
        if (!$delStmt) {
            continue;
        }
        foreach ($ids as $delId) {
            $idToDel = (int) $delId;
            $delStmt->bind_param('i', $idToDel);
            @$delStmt->execute();
        }
        $delStmt->close();
    }
}

/**
 * Collapse rows whose `template_key` differs only by leading/trailing whitespace (breaks dedupe + shows duplicate labels).
 */
function btb_email_template_normalize_trim_duplicate_keys($conn): void
{
    if (!$conn || !function_exists('btb_db_table_exists') || !btb_db_table_exists($conn, 'email_template_settings')) {
        return;
    }
    $res = @$conn->query('SELECT `template_key` FROM `email_template_settings`');
    if (!$res) {
        return;
    }
    $rawKeys = [];
    while ($row = $res->fetch_assoc()) {
        $raw = (string) ($row['template_key'] ?? '');
        if ($raw !== '') {
            $rawKeys[$raw] = true;
        }
    }
    foreach (array_keys($rawKeys) as $raw) {
        $canon = btb_email_template_normalize_identity_key($raw);
        if ($canon === '' || $canon === $raw) {
            continue;
        }
        $exists = fetchOne($conn, 'SELECT `template_key` FROM `email_template_settings` WHERE `template_key` = ? LIMIT 1', [$canon]);
        if (is_array($exists) && !empty($exists)) {
            $del = $conn->prepare('DELETE FROM `email_template_settings` WHERE `template_key` = ? LIMIT 1');
            if ($del) {
                $del->bind_param('s', $raw);
                @$del->execute();
                $del->close();
            }
            continue;
        }
        $upd = $conn->prepare('UPDATE `email_template_settings` SET `template_key` = ? WHERE `template_key` = ?');
        if ($upd) {
            $upd->bind_param('ss', $canon, $raw);
            @$upd->execute();
            $upd->close();
        }
    }
}

function btb_email_templates_api_data($conn): array
{
    $defaults = btb_email_template_defaults();
    $defaultsForAdmin = $defaults;
    if (!$conn || !btb_ensure_email_templates_settings_table($conn)) {
        return ['templates' => $defaultsForAdmin];
    }
    $res = @$conn->query(
        'SELECT `template_key`, `display_name`, `scenario_notes`, `audience`, `subject`, `heading`, `body`, `body_after`, `body_contact`, `cta_label`, `cta_url`, `image_url`
         FROM `email_template_settings`
         ORDER BY `template_key` ASC'
    );
    if (!$res) {
        return ['templates' => $defaults];
    }
    $score = static function (array $r): int {
        return strlen((string) ($r['subject'] ?? ''))
            + strlen((string) ($r['body'] ?? ''))
            + strlen((string) ($r['body_after'] ?? ''))
            + strlen((string) ($r['body_contact'] ?? ''))
            + strlen((string) ($r['heading'] ?? ''));
    };
    /** @var array<string, array<string, mixed>> $dbBest */
    $dbBest = [];
    while ($row = $res->fetch_assoc()) {
        if (!is_array($row)) {
            continue;
        }
        $canon = btb_email_template_normalize_identity_key((string) ($row['template_key'] ?? ''));
        if ($canon === '') {
            continue;
        }
        $patch = [
            'template_key' => $canon,
            'display_name' => (string) ($row['display_name'] ?? ''),
            'scenario_notes' => (string) ($row['scenario_notes'] ?? ''),
            'audience' => (string) ($row['audience'] ?? 'guest'),
            'subject' => (string) ($row['subject'] ?? ''),
            'heading' => (string) ($row['heading'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'body_after' => (string) ($row['body_after'] ?? ''),
            'body_contact' => (string) ($row['body_contact'] ?? ''),
            'cta_label' => (string) ($row['cta_label'] ?? ''),
            'cta_url' => (string) ($row['cta_url'] ?? ''),
            'image_url' => (string) ($row['image_url'] ?? ''),
        ];
        if (!isset($dbBest[$canon]) || $score($patch) > $score($dbBest[$canon])) {
            $dbBest[$canon] = $patch;
        }
    }
    $patchCols = [
        'display_name',
        'scenario_notes',
        'audience',
        'subject',
        'heading',
        'body',
        'body_after',
        'body_contact',
        'cta_label',
        'cta_url',
        'image_url',
    ];
    $merged = [];
    foreach ($defaultsForAdmin as $def) {
        if (!is_array($def)) {
            continue;
        }
        $k = (string) ($def['template_key'] ?? '');
        if ($k === '') {
            continue;
        }
        $dbRow = $dbBest[$k] ?? null;
        if (!is_array($dbRow)) {
            $merged[] = $def;
            continue;
        }
        $overlay = [];
        foreach ($patchCols as $col) {
            if (array_key_exists($col, $dbRow)) {
                $overlay[$col] = $dbRow[$col];
            }
        }
        $merged[] = array_merge($def, $overlay, ['template_key' => $k]);
    }

    $uniq = [];
    $seenKeys = [];
    foreach ($merged as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tk = btb_email_template_normalize_identity_key((string) ($row['template_key'] ?? ''));
        if ($tk === '' || isset($seenKeys[$tk])) {
            continue;
        }
        $seenKeys[$tk] = true;
        $row['template_key'] = $tk;
        $uniq[] = $row;
    }

    /** Labels shown in admin dropdown: fall back to code defaults when DB reused the same title on many rows (broken editing UX). */
    $defaultLabels = [];
    foreach ($defaultsForAdmin as $dr) {
        if (!is_array($dr)) {
            continue;
        }
        $dk = (string) ($dr['template_key'] ?? '');
        if ($dk !== '') {
            $defaultLabels[$dk] = trim((string) ($dr['display_name'] ?? ''));
        }
    }
    $labelCounts = [];
    foreach ($uniq as $row) {
        if (!is_array($row)) {
            continue;
        }
        $lbl = trim((string) ($row['display_name'] ?? ''));
        if ($lbl !== '') {
            $labelCounts[$lbl] = ($labelCounts[$lbl] ?? 0) + 1;
        }
    }
    foreach ($uniq as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $tk = btb_email_template_normalize_identity_key((string) ($row['template_key'] ?? ''));
        $dbLbl = trim((string) ($row['display_name'] ?? ''));
        $fallback = ($defaultLabels[$tk] ?? '') !== '' ? $defaultLabels[$tk] : $tk;
        $ambiguous = ($dbLbl === '' || ($labelCounts[$dbLbl] ?? 0) > 1);
        /** Wrong legacy DB labels (e.g. room-request copy pasted onto chat rows) made chat templates invisible in the picker. */
        $isChatTpl = ($tk === 'host_chat_guest_message_host' || $tk === 'guest_chat_staff_reply_guest');
        $chatLblLooksWrong = $isChatTpl && $dbLbl !== '' && stripos($dbLbl, 'chat') === false;
        $row['dropdown_label'] = ($ambiguous || $chatLblLooksWrong) ? $fallback : $dbLbl;
        $row['display_name_is_ambiguous'] = $ambiguous || $chatLblLooksWrong;
    }
    unset($row);

    return ['templates' => $uniq];
}

function btb_save_email_template_from_post($conn, array $post): array
{
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    if (!btb_ensure_email_templates_settings_table($conn)) {
        return ['success' => false, 'error' => 'Could not ensure email_template_settings'];
    }
    $key = btb_email_template_normalize_identity_key(trim((string) ($post['template_key'] ?? '')));
    if ($key === '') {
        return ['success' => false, 'error' => 'template_key is required'];
    }
    $knownTpl = btb_email_template_known_keys_map();
    if (!isset($knownTpl[$key])) {
        return ['success' => false, 'error' => 'Unknown template_key'];
    }
    $display = trim((string) ($post['display_name'] ?? ''));
    $scenarioNotes = (string) ($post['scenario_notes'] ?? '');
    $aud = trim((string) ($post['audience'] ?? 'guest'));
    if ($aud !== 'host') {
        $aud = 'guest';
    }
    $subject = trim((string) ($post['subject'] ?? ''));
    $heading = trim((string) ($post['heading'] ?? ''));
    $body = (string) ($post['body'] ?? '');
    $bodyAfter = (string) ($post['body_after'] ?? '');
    $bodyContact = (string) ($post['body_contact'] ?? '');
    $ctaLabel = trim((string) ($post['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($post['cta_url'] ?? ''));
    $imageUrl = trim((string) ($post['image_url'] ?? ''));
    if ($imageUrl !== '') {
        $normImg = btb_email_safe_public_url($imageUrl);
        if ($normImg !== '') {
            $imageUrl = $normImg;
        }
    }
    if (function_exists('mb_substr')) {
        $key = mb_substr($key, 0, 80, 'UTF-8');
        $display = mb_substr($display, 0, 255, 'UTF-8');
        $scenarioNotes = mb_substr($scenarioNotes, 0, 60000, 'UTF-8');
        $subject = mb_substr($subject, 0, 255, 'UTF-8');
        $heading = mb_substr($heading, 0, 500, 'UTF-8');
        $bodyAfter = mb_substr($bodyAfter, 0, 60000, 'UTF-8');
        $bodyContact = mb_substr($bodyContact, 0, 60000, 'UTF-8');
        $ctaLabel = mb_substr($ctaLabel, 0, 255, 'UTF-8');
        $ctaUrl = mb_substr($ctaUrl, 0, 1024, 'UTF-8');
        $imageUrl = mb_substr($imageUrl, 0, 1024, 'UTF-8');
    } else {
        $key = substr($key, 0, 80);
        $display = substr($display, 0, 255);
        $scenarioNotes = substr($scenarioNotes, 0, 60000);
        $subject = substr($subject, 0, 255);
        $heading = substr($heading, 0, 500);
        $bodyAfter = substr($bodyAfter, 0, 60000);
        $bodyContact = substr($bodyContact, 0, 60000);
        $ctaLabel = substr($ctaLabel, 0, 255);
        $ctaUrl = substr($ctaUrl, 0, 1024);
        $imageUrl = substr($imageUrl, 0, 1024);
    }
    $sql = 'INSERT INTO `email_template_settings`
        (`template_key`, `display_name`, `scenario_notes`, `audience`, `subject`, `heading`, `body`, `body_after`, `body_contact`, `cta_label`, `cta_url`, `image_url`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          `display_name` = VALUES(`display_name`),
          `scenario_notes` = VALUES(`scenario_notes`),
          `audience` = VALUES(`audience`),
          `subject` = VALUES(`subject`),
          `heading` = VALUES(`heading`),
          `body` = VALUES(`body`),
          `body_after` = VALUES(`body_after`),
          `body_contact` = VALUES(`body_contact`),
          `cta_label` = VALUES(`cta_label`),
          `cta_url` = VALUES(`cta_url`),
          `image_url` = VALUES(`image_url`)';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    $stmt->bind_param('ssssssssssss', $key, $display, $scenarioNotes, $aud, $subject, $heading, $body, $bodyAfter, $bodyContact, $ctaLabel, $ctaUrl, $imageUrl);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();
        return ['success' => false, 'error' => $err];
    }
    $stmt->close();
    return ['success' => true];
}

function btb_email_template_get_one($conn, string $templateKey): ?array
{
    $templateKey = btb_email_template_normalize_identity_key(trim($templateKey));
    if ($templateKey === '' || !$conn) {
        return null;
    }
    if (!btb_ensure_email_templates_settings_table($conn)) {
        return null;
    }
    $stmt = $conn->prepare(
        'SELECT `template_key`, `display_name`, `scenario_notes`, `audience`, `subject`, `heading`, `body`, `body_after`, `body_contact`, `cta_label`, `cta_url`, `image_url`
         FROM `email_template_settings`
         WHERE `template_key` = ?
         ORDER BY `updated_at` DESC
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $templateKey);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }
    $scenarioNotes = (string) ($row['scenario_notes'] ?? '');

    return [
        'template_key' => $templateKey,
        'display_name' => (string) ($row['display_name'] ?? ''),
        'scenario_notes' => $scenarioNotes,
        'audience' => (string) ($row['audience'] ?? 'guest'),
        'subject' => (string) ($row['subject'] ?? ''),
        'heading' => (string) ($row['heading'] ?? ''),
        'body' => (string) ($row['body'] ?? ''),
        'body_after' => (string) ($row['body_after'] ?? ''),
        'body_contact' => (string) ($row['body_contact'] ?? ''),
        'cta_label' => (string) ($row['cta_label'] ?? ''),
        'cta_url' => (string) ($row['cta_url'] ?? ''),
        'image_url' => (string) ($row['image_url'] ?? ''),
    ];
}

/**
 * Public “My Bookings” page URL (guest pays from here after confirmation).
 */
function btb_guest_orders_url(): string
{
    $site = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';

    return $site !== '' ? ($site . '/order.html') : '/order.html';
}

/**
 * Deep link: open My Bookings with room payment flow (guest must match booking email or pass confirmation code).
 *
 * @param array<string,mixed> $booking bookings row with id + confirmation_code
 */
function btb_guest_room_pay_url(array $booking): string
{
    $base = btb_guest_orders_url();
    $bid = (int) ($booking['id'] ?? 0);
    $code = trim((string) ($booking['confirmation_code'] ?? ''));
    if ($bid <= 0 || $code === '') {
        return $base;
    }

    return $base . '?pay=room&booking_id=' . $bid . '&confirmation_code=' . rawurlencode($code);
}

/**
 * Deep link for wellness booking payment after staff confirmation.
 *
 * @param array<string,mixed> $mb massage_bookings row
 */
function btb_guest_massage_pay_url(array $mb): string
{
    $base = btb_guest_orders_url();
    $bid = (int) ($mb['id'] ?? 0);
    $code = trim((string) ($mb['confirmation_code'] ?? ''));
    if ($bid <= 0 || $code === '') {
        return $base;
    }

    return $base . '?pay=massage&booking_id=' . $bid . '&confirmation_code=' . rawurlencode($code);
}

/**
 * Defaults for “My Bookings” summary (cleaning, pets, Canadian taxes) — public API + admin.
 *
 * @return array<string, float|int|string>
 */
function btb_my_bookings_pricing_defaults(): array
{
    return [
        'cleaning_label' => 'Cleaning fee',
        'cleaning_amount_cad' => 60.0,
        'cleaning_loki_suite_amount_cad' => 60.0,
        'cleaning_the_nouk_amount_cad' => 60.0,
        'cleaning_vrienden_amount_cad' => 60.0,
        'cleaning_kelder_amount_cad' => 100.0,
        'pets_label' => 'Dogs',
        'pets_max_qty' => 2,
        'pets_amount_per_dog_cad' => 75.0,
        'tax1_label' => 'GST',
        'tax1_percent' => 0.0,
        'tax2_label' => 'PST',
        'tax2_percent' => 0.0,
        'tax3_label' => 'Tax 3',
        'tax3_percent' => 0.0,
    ];
}

/**
 * Add per-room cleaning fee columns (migrate from legacy standard + Kelder tiers).
 */
function btb_my_bookings_pricing_ensure_per_room_cleaning_columns($conn): void
{
    if (!$conn) {
        return;
    }
    $add = static function (string $col, string $def) use ($conn): bool {
        $esc = $conn->real_escape_string($col);
        $res = @$conn->query("SHOW COLUMNS FROM `my_bookings_pricing_settings` LIKE '{$esc}'");
        if ($res && $res->num_rows > 0) {
            return false;
        }
        $ok = @$conn->query(
            "ALTER TABLE `my_bookings_pricing_settings` ADD COLUMN `{$col}` {$def}"
        );
        if (!$ok) {
            error_log('btb_my_bookings_pricing_ensure_per_room_cleaning_columns: ' . $conn->error);
        }

        return (bool) $ok;
    };

    $added = false;
    if ($add('cleaning_loki_suite_amount_cad', 'DECIMAL(10,2) NOT NULL DEFAULT 60.00')) {
        $added = true;
    }
    if ($add('cleaning_the_nouk_amount_cad', 'DECIMAL(10,2) NOT NULL DEFAULT 60.00')) {
        $added = true;
    }
    if ($add('cleaning_vrienden_amount_cad', 'DECIMAL(10,2) NOT NULL DEFAULT 60.00')) {
        $added = true;
    }

    if ($added) {
        @$conn->query(
            'UPDATE `my_bookings_pricing_settings` SET
                `cleaning_loki_suite_amount_cad` = `cleaning_amount_cad`,
                `cleaning_the_nouk_amount_cad` = `cleaning_amount_cad`,
                `cleaning_vrienden_amount_cad` = `cleaning_amount_cad`
             WHERE `id` = 1'
        );
    }
}

/** @return array<string, string> room display name (lower) => pricing settings key */
function btb_room_cleaning_fee_setting_keys(): array
{
    return [
        'loki suite' => 'cleaning_loki_suite_amount_cad',
        'the nouk' => 'cleaning_the_nouk_amount_cad',
        'vrienden' => 'cleaning_vrienden_amount_cad',
        'kelder' => 'cleaning_kelder_amount_cad',
    ];
}

/**
 * Add optional tax columns to existing pricing settings rows.
 */
function btb_my_bookings_pricing_ensure_tax_columns($conn): void
{
    if (!$conn) {
        return;
    }
    $add = static function (string $col, string $def) use ($conn): void {
        $esc = $conn->real_escape_string($col);
        $res = @$conn->query("SHOW COLUMNS FROM `my_bookings_pricing_settings` LIKE '{$esc}'");
        if ($res && $res->num_rows > 0) {
            return;
        }
        if (!@$conn->query("ALTER TABLE `my_bookings_pricing_settings` ADD COLUMN `{$col}` {$def}")) {
            error_log('btb_my_bookings_pricing_ensure_tax_columns: ' . $conn->error);
        }
    };

    $add('tax3_label', 'VARCHAR(191) NOT NULL DEFAULT \'Tax 3\' AFTER `tax2_percent`');
    $add('tax3_percent', 'DECIMAL(7,3) NOT NULL DEFAULT 0.000 AFTER `tax3_label`');
}

/**
 * Ensure single-row settings table for My Bookings pricing lines.
 */
function btb_ensure_my_bookings_pricing_settings_table($conn): bool
{
    if (!$conn) {
        return false;
    }
    $sql = 'CREATE TABLE IF NOT EXISTS `my_bookings_pricing_settings` (
        `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        `cleaning_label` VARCHAR(191) NOT NULL DEFAULT \'Cleaning fee\',
        `cleaning_amount_cad` DECIMAL(10,2) NOT NULL DEFAULT 60.00,
        `cleaning_kelder_amount_cad` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
        `pets_label` VARCHAR(191) NOT NULL DEFAULT \'Dogs\',
        `pets_max_qty` TINYINT UNSIGNED NOT NULL DEFAULT 2,
        `pets_amount_per_dog_cad` DECIMAL(10,2) NOT NULL DEFAULT 75.00,
        `tax1_label` VARCHAR(191) NOT NULL DEFAULT \'GST\',
        `tax1_percent` DECIMAL(7,3) NOT NULL DEFAULT 0.000,
        `tax2_label` VARCHAR(191) NOT NULL DEFAULT \'PST\',
        `tax2_percent` DECIMAL(7,3) NOT NULL DEFAULT 0.000,
        `tax3_label` VARCHAR(191) NOT NULL DEFAULT \'Tax 3\',
        `tax3_percent` DECIMAL(7,3) NOT NULL DEFAULT 0.000,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!@$conn->query($sql)) {
        error_log('btb_ensure_my_bookings_pricing_settings_table: ' . $conn->error);

        return false;
    }
    btb_my_bookings_pricing_ensure_per_room_cleaning_columns($conn);
    btb_my_bookings_pricing_ensure_tax_columns($conn);
    $d = btb_my_bookings_pricing_defaults();
    $stmt = @$conn->prepare(
        'INSERT IGNORE INTO `my_bookings_pricing_settings` (`id`, `cleaning_label`, `cleaning_amount_cad`, `cleaning_kelder_amount_cad`, `pets_label`, `pets_max_qty`, `pets_amount_per_dog_cad`, `tax1_label`, `tax1_percent`, `tax2_label`, `tax2_percent`, `tax3_label`, `tax3_percent`)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $cl = (string) $d['cleaning_label'];
        $ca = (float) $d['cleaning_amount_cad'];
        $ck = (float) $d['cleaning_kelder_amount_cad'];
        $pl = (string) $d['pets_label'];
        $pm = (int) $d['pets_max_qty'];
        $pp = (float) $d['pets_amount_per_dog_cad'];
        $t1l = (string) $d['tax1_label'];
        $t1p = (float) $d['tax1_percent'];
        $t2l = (string) $d['tax2_label'];
        $t2p = (float) $d['tax2_percent'];
        $t3l = (string) $d['tax3_label'];
        $t3p = (float) $d['tax3_percent'];
        $stmt->bind_param(
            'sddsidsdsdsd',
            $cl,
            $ca,
            $ck,
            $pl,
            $pm,
            $pp,
            $t1l,
            $t1p,
            $t2l,
            $t2p,
            $t3l,
            $t3p
        );
        @$stmt->execute();
        $stmt->close();
    }

    return true;
}

/**
 * @return array<string, float|int|string>
 */
function btb_my_bookings_pricing_api_data($conn): array
{
    $def = btb_my_bookings_pricing_defaults();
    if (!$conn || !btb_ensure_my_bookings_pricing_settings_table($conn)) {
        return $def;
    }
    $res = @$conn->query(
        'SELECT `cleaning_label`, `cleaning_amount_cad`, `cleaning_loki_suite_amount_cad`, `cleaning_the_nouk_amount_cad`, `cleaning_vrienden_amount_cad`, `cleaning_kelder_amount_cad`, `pets_label`, `pets_max_qty`, `pets_amount_per_dog_cad`, `tax1_label`, `tax1_percent`, `tax2_label`, `tax2_percent`, `tax3_label`, `tax3_percent`
        FROM `my_bookings_pricing_settings` WHERE `id` = 1 LIMIT 1'
    );
    if (!$res || $res->num_rows === 0) {
        return $def;
    }
    $row = $res->fetch_assoc();
    if (!is_array($row)) {
        return $def;
    }
    $clampPct = static function ($v): float {
        $n = (float) $v;
        if ($n < 0) {
            return 0.0;
        }
        if ($n > 100) {
            return 100.0;
        }

        return round($n, 3);
    };
    $clampMoney = static function ($v): float {
        $n = (float) $v;
        if ($n < 0) {
            return 0.0;
        }

        return round($n, 2);
    };

    $legacyStandard = $clampMoney($row['cleaning_amount_cad'] ?? $def['cleaning_amount_cad']);
    $loki = isset($row['cleaning_loki_suite_amount_cad'])
        ? $clampMoney($row['cleaning_loki_suite_amount_cad'])
        : $legacyStandard;
    $nouk = isset($row['cleaning_the_nouk_amount_cad'])
        ? $clampMoney($row['cleaning_the_nouk_amount_cad'])
        : $legacyStandard;
    $vrienden = isset($row['cleaning_vrienden_amount_cad'])
        ? $clampMoney($row['cleaning_vrienden_amount_cad'])
        : $legacyStandard;

    return [
        'cleaning_label' => trim((string) ($row['cleaning_label'] ?? '')) !== ''
            ? trim((string) $row['cleaning_label'])
            : $def['cleaning_label'],
        'cleaning_amount_cad' => $legacyStandard,
        'cleaning_loki_suite_amount_cad' => $loki,
        'cleaning_the_nouk_amount_cad' => $nouk,
        'cleaning_vrienden_amount_cad' => $vrienden,
        'cleaning_kelder_amount_cad' => $clampMoney($row['cleaning_kelder_amount_cad'] ?? $def['cleaning_kelder_amount_cad']),
        'pets_label' => trim((string) ($row['pets_label'] ?? '')) !== ''
            ? trim((string) $row['pets_label'])
            : $def['pets_label'],
        'pets_max_qty' => max(1, min(9, (int) ($row['pets_max_qty'] ?? $def['pets_max_qty']))),
        'pets_amount_per_dog_cad' => $clampMoney($row['pets_amount_per_dog_cad'] ?? $def['pets_amount_per_dog_cad']),
        'tax1_label' => trim((string) ($row['tax1_label'] ?? '')) !== ''
            ? trim((string) $row['tax1_label'])
            : $def['tax1_label'],
        'tax1_percent' => $clampPct($row['tax1_percent'] ?? $def['tax1_percent']),
        'tax2_label' => trim((string) ($row['tax2_label'] ?? '')) !== ''
            ? trim((string) $row['tax2_label'])
            : $def['tax2_label'],
        'tax2_percent' => $clampPct($row['tax2_percent'] ?? $def['tax2_percent']),
        'tax3_label' => trim((string) ($row['tax3_label'] ?? '')) !== ''
            ? trim((string) $row['tax3_label'])
            : $def['tax3_label'],
        'tax3_percent' => $clampPct($row['tax3_percent'] ?? $def['tax3_percent']),
    ];
}

/**
 * @param array<string,mixed> $post
 *
 * @return array{success: bool, error?: string}
 */
function btb_save_my_bookings_pricing_from_post($conn, array $post): array
{
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    if (!btb_ensure_my_bookings_pricing_settings_table($conn)) {
        return ['success' => false, 'error' => 'Could not ensure my_bookings_pricing_settings'];
    }
    $def = btb_my_bookings_pricing_defaults();
    $trim191 = static function ($s) use ($def) {
        $t = trim((string) $s);
        if ($t === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, 191, 'UTF-8');
        }

        return substr($t, 0, 191);
    };
    $clampPct = static function ($v) use ($def) {
        $n = (float) str_replace(',', '.', (string) $v);
        if ($n < 0) {
            $n = 0;
        }
        if ($n > 100) {
            $n = 100;
        }

        return round($n, 3);
    };
    $clampMoney = static function ($v) {
        $n = (float) str_replace(',', '.', (string) $v);
        if ($n < 0) {
            $n = 0;
        }

        return round($n, 2);
    };

    $cleaning_label = $trim191($post['cleaning_label'] ?? $def['cleaning_label']);
    if ($cleaning_label === '') {
        $cleaning_label = (string) $def['cleaning_label'];
    }
    $pets_label = $trim191($post['pets_label'] ?? $def['pets_label']);
    if ($pets_label === '') {
        $pets_label = (string) $def['pets_label'];
    }
    $tax1_label = $trim191($post['tax1_label'] ?? $def['tax1_label']);
    if ($tax1_label === '') {
        $tax1_label = (string) $def['tax1_label'];
    }
    $tax2_label = $trim191($post['tax2_label'] ?? $def['tax2_label']);
    if ($tax2_label === '') {
        $tax2_label = (string) $def['tax2_label'];
    }
    $tax3_label = $trim191($post['tax3_label'] ?? $def['tax3_label']);
    if ($tax3_label === '') {
        $tax3_label = (string) $def['tax3_label'];
    }

    $cleaning_loki_suite_amount_cad = $clampMoney(
        $post['cleaning_loki_suite_amount_cad'] ?? $post['cleaning_amount_cad'] ?? $def['cleaning_loki_suite_amount_cad']
    );
    $cleaning_the_nouk_amount_cad = $clampMoney(
        $post['cleaning_the_nouk_amount_cad'] ?? $post['cleaning_amount_cad'] ?? $def['cleaning_the_nouk_amount_cad']
    );
    $cleaning_vrienden_amount_cad = $clampMoney(
        $post['cleaning_vrienden_amount_cad'] ?? $post['cleaning_amount_cad'] ?? $def['cleaning_vrienden_amount_cad']
    );
    $cleaning_kelder_amount_cad = $clampMoney($post['cleaning_kelder_amount_cad'] ?? $def['cleaning_kelder_amount_cad']);
    $cleaning_amount_cad = $cleaning_loki_suite_amount_cad;
    $pets_max_qty = max(1, min(9, (int) ($post['pets_max_qty'] ?? $def['pets_max_qty'])));
    $pets_amount_per_dog_cad = $clampMoney($post['pets_amount_per_dog_cad'] ?? $def['pets_amount_per_dog_cad']);
    $tax1_percent = $clampPct($post['tax1_percent'] ?? $def['tax1_percent']);
    $tax2_percent = $clampPct($post['tax2_percent'] ?? $def['tax2_percent']);
    $tax3_percent = $clampPct($post['tax3_percent'] ?? $def['tax3_percent']);

    btb_my_bookings_pricing_ensure_per_room_cleaning_columns($conn);
    btb_my_bookings_pricing_ensure_tax_columns($conn);

    $sql = 'INSERT INTO `my_bookings_pricing_settings` (
        `id`, `cleaning_label`, `cleaning_amount_cad`, `cleaning_loki_suite_amount_cad`, `cleaning_the_nouk_amount_cad`, `cleaning_vrienden_amount_cad`, `cleaning_kelder_amount_cad`, `pets_label`, `pets_max_qty`, `pets_amount_per_dog_cad`, `tax1_label`, `tax1_percent`, `tax2_label`, `tax2_percent`, `tax3_label`, `tax3_percent`
    ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        `cleaning_label` = VALUES(`cleaning_label`),
        `cleaning_amount_cad` = VALUES(`cleaning_amount_cad`),
        `cleaning_loki_suite_amount_cad` = VALUES(`cleaning_loki_suite_amount_cad`),
        `cleaning_the_nouk_amount_cad` = VALUES(`cleaning_the_nouk_amount_cad`),
        `cleaning_vrienden_amount_cad` = VALUES(`cleaning_vrienden_amount_cad`),
        `cleaning_kelder_amount_cad` = VALUES(`cleaning_kelder_amount_cad`),
        `pets_label` = VALUES(`pets_label`),
        `pets_max_qty` = VALUES(`pets_max_qty`),
        `pets_amount_per_dog_cad` = VALUES(`pets_amount_per_dog_cad`),
        `tax1_label` = VALUES(`tax1_label`),
        `tax1_percent` = VALUES(`tax1_percent`),
        `tax2_label` = VALUES(`tax2_label`),
        `tax2_percent` = VALUES(`tax2_percent`),
        `tax3_label` = VALUES(`tax3_label`),
        `tax3_percent` = VALUES(`tax3_percent`)';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    $stmt->bind_param(
        'sdddddsidsdsdsd',
        $cleaning_label,
        $cleaning_amount_cad,
        $cleaning_loki_suite_amount_cad,
        $cleaning_the_nouk_amount_cad,
        $cleaning_vrienden_amount_cad,
        $cleaning_kelder_amount_cad,
        $pets_label,
        $pets_max_qty,
        $pets_amount_per_dog_cad,
        $tax1_label,
        $tax1_percent,
        $tax2_label,
        $tax2_percent,
        $tax3_label,
        $tax3_percent
    );
    if (!$stmt->execute()) {
        $err = $stmt->error ?: 'Save failed';
        $stmt->close();

        return ['success' => false, 'error' => $err];
    }
    $stmt->close();

    return ['success' => true];
}

/** Room names that use Kelder cleaning fee tier. */
function btb_room_booking_is_kelder(string $roomName): bool
{
    return strcasecmp(trim($roomName), 'Kelder') === 0;
}

function btb_room_booking_cleaning_fee_cad($conn, string $roomName): float
{
    $pricing = btb_my_bookings_pricing_api_data($conn);
    $key = btb_room_cleaning_fee_setting_keys()[strtolower(trim($roomName))] ?? null;
    if ($key !== null && isset($pricing[$key])) {
        return round((float) $pricing[$key], 2);
    }
    if (btb_room_booking_is_kelder($roomName)) {
        return round((float) ($pricing['cleaning_kelder_amount_cad'] ?? 100), 2);
    }

    return round((float) ($pricing['cleaning_amount_cad'] ?? 60), 2);
}

/**
 * Dog fee: one flat amount per room booking when pets &gt; 0 (1 or 2 dogs — same charge).
 */
function btb_room_booking_pets_fee_cad($conn, int $pets): float
{
    $pets = max(0, min(2, $pets));
    if ($pets <= 0) {
        return 0.0;
    }
    $pricing = btb_my_bookings_pricing_api_data($conn);

    return round((float) ($pricing['pets_amount_per_dog_cad'] ?? 75), 2);
}

function btb_room_booking_nights(string $checkin, string $checkout): int
{
    $ci = DateTime::createFromFormat('Y-m-d', $checkin);
    $co = DateTime::createFromFormat('Y-m-d', $checkout);
    if (!$ci || !$co || $ci->format('Y-m-d') !== $checkin || $co->format('Y-m-d') !== $checkout) {
        return 0;
    }
    $n = (int) $ci->diff($co)->days;

    return max(0, $n);
}

/**
 * Public room booking form: nightly rate + fee labels for client-side estimate (pre-tax).
 *
 * @return array{nightly: ?float, cleaning: float, pets_fee: float, cleaning_label: string, pets_label: string, data_attrs: string}
 */
function btb_room_booking_public_pricing_context($conn, array $content, string $priceSlug, string $roomName): array
{
    $nightly = btb_room_price_nightly_amount($content, $priceSlug);
    $pricing = btb_my_bookings_pricing_api_data($conn);
    $cleaning = btb_room_booking_cleaning_fee_cad($conn, $roomName);
    $petsFee = btb_room_booking_pets_fee_cad($conn, 1);
    $cleaningLabel = trim((string) ($pricing['cleaning_label'] ?? ''));
    if ($cleaningLabel === '') {
        $cleaningLabel = 'Cleaning fee';
    }
    $petsLabel = trim((string) ($pricing['pets_label'] ?? ''));
    if ($petsLabel === '') {
        $petsLabel = 'Dogs';
    }

    $pairs = [
        'data-btb-nightly-rate' => $nightly !== null && $nightly > 0 ? (string) $nightly : '',
        'data-btb-cleaning-fee' => (string) $cleaning,
        'data-btb-pets-fee' => (string) $petsFee,
        'data-btb-cleaning-label' => $cleaningLabel,
        'data-btb-pets-label' => $petsLabel,
    ];
    $dataAttrs = '';
    foreach ($pairs as $key => $val) {
        $dataAttrs .= ' ' . $key . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
    }

    return [
        'nightly' => $nightly,
        'cleaning' => $cleaning,
        'pets_fee' => $petsFee,
        'cleaning_label' => $cleaningLabel,
        'pets_label' => $petsLabel,
        'data_attrs' => $dataAttrs,
    ];
}

/**
 * @return array{tax1: float, tax2: float, tax3: float, grand: float, tax1_label: string, tax2_label: string, tax3_label: string, tax1_percent: float, tax2_percent: float, tax3_percent: float}
 */
function btb_taxes_on_taxable_subtotal($conn, float $taxable): array
{
    $taxable = round(max(0, $taxable), 2);
    $pricing = btb_my_bookings_pricing_api_data($conn);
    $t1p = (float) ($pricing['tax1_percent'] ?? 0);
    $t2p = (float) ($pricing['tax2_percent'] ?? 0);
    $t3p = (float) ($pricing['tax3_percent'] ?? 0);
    $tax1 = ($t1p > 0) ? round($taxable * ($t1p / 100), 2) : 0.0;
    $tax2 = ($t2p > 0) ? round($taxable * ($t2p / 100), 2) : 0.0;
    $tax3 = ($t3p > 0) ? round($taxable * ($t3p / 100), 2) : 0.0;
    $grand = round($taxable + $tax1 + $tax2 + $tax3, 2);

    return [
        'tax1' => $tax1,
        'tax2' => $tax2,
        'tax3' => $tax3,
        'grand' => $grand,
        'tax1_label' => (string) ($pricing['tax1_label'] ?? 'GST'),
        'tax2_label' => (string) ($pricing['tax2_label'] ?? 'PST'),
        'tax3_label' => (string) ($pricing['tax3_label'] ?? 'Tax 3'),
        'tax1_percent' => $t1p,
        'tax2_percent' => $t2p,
        'tax3_percent' => $t3p,
    ];
}

/**
 * Pricing shape for non-taxed lines (currently wellness/massage bookings).
 *
 * @return array{tax1: float, tax2: float, tax3: float, grand: float, tax1_label: string, tax2_label: string, tax3_label: string, tax1_percent: float, tax2_percent: float, tax3_percent: float}
 */
function btb_no_taxes_on_taxable_subtotal($conn, float $taxable): array
{
    $taxable = round(max(0, $taxable), 2);
    $pricing = btb_my_bookings_pricing_api_data($conn);

    return [
        'tax1' => 0.0,
        'tax2' => 0.0,
        'tax3' => 0.0,
        'grand' => $taxable,
        'tax1_label' => (string) ($pricing['tax1_label'] ?? 'GST'),
        'tax2_label' => (string) ($pricing['tax2_label'] ?? 'PST'),
        'tax3_label' => (string) ($pricing['tax3_label'] ?? 'Tax 3'),
        'tax1_percent' => 0.0,
        'tax2_percent' => 0.0,
        'tax3_percent' => 0.0,
    ];
}

/**
 * Stripe / guest charge amount (taxable subtotal + taxes).
 */
function btb_grand_total_with_taxes($conn, float $taxable): float
{
    $t = btb_taxes_on_taxable_subtotal($conn, $taxable);

    return $t['grand'];
}

/**
 * Build room booking taxable subtotal (stay + cleaning + pets) for storage in bookings.total_amount.
 *
 * @param array<string,mixed> $opts room_name, checkin_date, checkout_date, pets, host_stay_subtotal|null, total_amount_manual bool
 *
 * @return array{stay_subtotal: float, cleaning_fee: float, pets_fee: float, taxable_subtotal: float, nights: int, nightly_rate: ?float}
 */
function btb_room_booking_compute_line_pricing($conn, array $opts): array
{
    $roomName = trim((string) ($opts['room_name'] ?? ''));
    $checkin = trim((string) ($opts['checkin_date'] ?? ''));
    $checkout = trim((string) ($opts['checkout_date'] ?? ''));
    $pets = max(0, min(2, (int) ($opts['pets'] ?? 0)));
    $manual = !empty($opts['total_amount_manual']);
    $hostStay = isset($opts['host_stay_subtotal']) && $opts['host_stay_subtotal'] !== null && $opts['host_stay_subtotal'] !== ''
        ? round((float) $opts['host_stay_subtotal'], 2)
        : null;

    $nights = btb_room_booking_nights($checkin, $checkout);
    $nightly = null;
    $stay = 0.0;

    if ($manual && $hostStay !== null && $hostStay >= 0) {
        $stay = $hostStay;
        if ($nights > 0) {
            $nightly = round($stay / $nights, 2);
        }
    } elseif (isset($opts['stay_subtotal']) && $opts['stay_subtotal'] !== '' && $opts['stay_subtotal'] !== null) {
        $stay = round((float) $opts['stay_subtotal'], 2);
    } elseif (isset($opts['nightly_rate']) && $opts['nightly_rate'] !== null && $opts['nightly_rate'] !== '') {
        $nightly = (float) $opts['nightly_rate'];
        if ($nightly <= 0) {
            throw new InvalidArgumentException('This room has no nightly rate configured in the admin.');
        }
        $stay = round($nightly * $nights, 2);
    } else {
        throw new InvalidArgumentException('nightly_rate or stay_subtotal is required to compute room pricing.');
    }

    $cleaning = btb_room_booking_cleaning_fee_cad($conn, $roomName);
    $petsFee = btb_room_booking_pets_fee_cad($conn, $pets);
    $taxable = round($stay + $cleaning + $petsFee, 2);

    return [
        'stay_subtotal' => $stay,
        'cleaning_fee' => $cleaning,
        'pets_fee' => $petsFee,
        'taxable_subtotal' => $taxable,
        'nights' => $nights,
        'nightly_rate' => $nightly,
    ];
}

/**
 * Idempotent columns for host-edited booking pricing.
 */
function btb_bookings_ensure_admin_price_columns(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $defs = [
        'bookings' => [
            ['total_amount_manual', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['host_stay_subtotal', 'DECIMAL(10,2) NULL DEFAULT NULL'],
        ],
        'massage_bookings' => [
            ['total_amount_manual', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['host_service_subtotal', 'DECIMAL(10,2) NULL DEFAULT NULL'],
        ],
    ];
    foreach ($defs as $table => $cols) {
        $chk = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        if (!$chk || $chk->num_rows === 0) {
            continue;
        }
        foreach ($cols as [$name, $definition]) {
            $esc = $conn->real_escape_string($name);
            $c = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$esc}'");
            if ($c && $c->num_rows > 0) {
                continue;
            }
            @$conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
        }
    }
}

function btb_admin_may_edit_booking(): bool
{
    if (function_exists('btbJwtIsAdmin') && btbJwtIsAdmin()) {
        return true;
    }
    if (function_exists('isAdminAuthenticated') && isAdminAuthenticated()) {
        return true;
    }

    return false;
}

/**
 * Persist floorplan admin POST into floorplan_settings (canonical table only).
 *
 * @param array<string,mixed> $post
 * @return array{success: bool, error?: string, error_details?: string}
 */
function btb_save_floorplan_from_post($conn, array $post): array {
    if (!$conn) {
        return ['success' => false, 'error' => 'No database connection'];
    }
    try {
        try {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'floorplan_title'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN floorplan_title VARCHAR(255) DEFAULT 'Common areas'");
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'floorplan_subtitle'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN floorplan_subtitle TEXT DEFAULT 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.'");
            }
        } catch (Exception $e) {
            error_log('btb_save_floorplan_from_post: column check (title/subtitle): ' . $e->getMessage());
        }

        $floorplan_title = $post['floorplanTitle'] ?? '';
        $floorplan_subtitle = $post['floorplanSubtitle'] ?? '';
        $basement_subtitle = $post['basementSubtitle'] ?? '';
        $basement_description = $post['basementDescription'] ?? '';
        $basement_image_url = $post['basementImageUrl'] ?? '';
        $ground_subtitle = $post['groundSubtitle'] ?? '';
        $ground_description = $post['groundDescription'] ?? '';
        $ground_queen_image = $post['groundQueenImage'] ?? $post['ground_image_url'] ?? '';
        $ground_twin_image = $post['groundTwinImage'] ?? '';
        $loft_subtitle = $post['loftSubtitle'] ?? '';
        $loft_description = $post['loftDescription'] ?? '';
        $loft_image_url = $post['loftImageUrl'] ?? '';
        $basement_gallery = $post['basementGallery'] ?? '[]';
        $ground_gallery = $post['groundGallery'] ?? '[]';
        $loft_gallery = $post['loftGallery'] ?? '[]';

        error_log('btb_save_floorplan_from_post: ground_description: ' . substr((string) $ground_description, 0, 100));
        error_log('btb_save_floorplan_from_post: galleries basement/ground/loft lengths: ' . strlen((string) $basement_gallery) . ' / ' . strlen((string) $ground_gallery) . ' / ' . strlen((string) $loft_gallery));

        try {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'basement_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query('ALTER TABLE floorplan_settings ADD COLUMN basement_gallery TEXT DEFAULT NULL');
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'ground_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query('ALTER TABLE floorplan_settings ADD COLUMN ground_gallery TEXT DEFAULT NULL');
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'loft_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query('ALTER TABLE floorplan_settings ADD COLUMN loft_gallery TEXT DEFAULT NULL');
            }
        } catch (Exception $e) {
            error_log('btb_save_floorplan_from_post: column check (galleries): ' . $e->getMessage());
        }

        $stmt = $conn->prepare('INSERT INTO floorplan_settings (
                               id, floorplan_title, floorplan_subtitle, basement_subtitle, basement_description, basement_image_url,
                               basement_gallery, ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                               ground_gallery, loft_subtitle, loft_description, loft_image_url, loft_gallery
                               ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                               floorplan_title = ?, floorplan_subtitle = ?, basement_subtitle = ?, basement_description = ?, basement_image_url = ?,
                               basement_gallery = ?, ground_subtitle = ?, ground_description = ?, ground_queen_image = ?, ground_twin_image = ?,
                               ground_gallery = ?, loft_subtitle = ?, loft_description = ?, loft_image_url = ?, loft_gallery = ?');

        if (!$stmt) {
            $error = $conn->error;
            error_log('btb_save_floorplan_from_post: prepare failed - ' . $error);

            return ['success' => false, 'error' => 'Database prepare failed: ' . $error];
        }

        $paramTypes = str_repeat('s', 30);
        $bound = $stmt->bind_param(
            $paramTypes,
            $floorplan_title, $floorplan_subtitle, $basement_subtitle, $basement_description, $basement_image_url,
            $basement_gallery, $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
            $ground_gallery, $loft_subtitle, $loft_description, $loft_image_url, $loft_gallery,
            $floorplan_title, $floorplan_subtitle, $basement_subtitle, $basement_description, $basement_image_url,
            $basement_gallery, $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
            $ground_gallery, $loft_subtitle, $loft_description, $loft_image_url, $loft_gallery
        );
        if (!$bound) {
            $error = $stmt->error ?: 'Unknown bind_param error';
            error_log('btb_save_floorplan_from_post: bind_param failed - ' . $error);
            $stmt->close();

            return ['success' => false, 'error' => 'bind_param failed: ' . $error];
        }

        error_log('btb_save_floorplan_from_post: execute gallery preview basement=' . substr((string) $basement_gallery, 0, 200));

        if (!$stmt->execute()) {
            $error = $stmt->error ?: $conn->error;
            error_log('btb_save_floorplan_from_post: execute error - ' . $error);
            $stmt->close();
            if (strpos($error, 'floorplan_title') !== false || strpos($error, 'floorplan_subtitle') !== false) {
                return [
                    'success' => false,
                    'error' => 'Database columns missing. Please run add_floorplan_title_fields.php to add the required columns.',
                    'error_details' => $error,
                ];
            }

            return ['success' => false, 'error' => $error, 'error_details' => $error];
        }
        $stmt->close();

        if (function_exists('dbTableHasColumn')) {
            foreach (['basement_gallery_overlay_text', 'ground_gallery_overlay_text', 'loft_gallery_overlay_text'] as $fpOvCol) {
                if (!dbTableHasColumn($conn, 'floorplan_settings', $fpOvCol)) {
                    @$conn->query("ALTER TABLE floorplan_settings ADD COLUMN `{$fpOvCol}` TEXT NULL");
                }
            }
        }
        if (isset($post['basementGalleryOverlayText']) || isset($post['groundGalleryOverlayText']) || isset($post['loftGalleryOverlayText'])) {
            $ovB = isset($post['basementGalleryOverlayText']) ? (string) $post['basementGalleryOverlayText'] : '';
            $ovG = isset($post['groundGalleryOverlayText']) ? (string) $post['groundGalleryOverlayText'] : '';
            $ovL = isset($post['loftGalleryOverlayText']) ? (string) $post['loftGalleryOverlayText'] : '';
            $ovStmt = $conn->prepare('UPDATE floorplan_settings SET basement_gallery_overlay_text = ?, ground_gallery_overlay_text = ?, loft_gallery_overlay_text = ? WHERE id = 1');
            if ($ovStmt) {
                $ovStmt->bind_param('sss', $ovB, $ovG, $ovL);
                if ($ovStmt->execute()) {
                    error_log('btb_save_floorplan_from_post: saved gallery overlay text fields');
                }
                $ovStmt->close();
            }
        }

        $verify = $conn->query('SELECT ground_description, basement_gallery, ground_gallery, loft_gallery FROM floorplan_settings WHERE id = 1');
        if ($verify && $verify->num_rows > 0) {
            $saved = $verify->fetch_assoc();
            error_log('btb_save_floorplan_from_post: verified ground_description: ' . substr((string) ($saved['ground_description'] ?? ''), 0, 100));
        }

        return ['success' => true];
    } catch (Throwable $e) {
        error_log('btb_save_floorplan_from_post: ' . $e->getMessage());

        return [
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage(),
            'error_details' => $e->getFile() . ':' . $e->getLine(),
        ];
    }
}

/**
 * The line room_cards_settings id=1 may contain empty card_title / card_description / card_image_url, then
 * btb_merge_cms_table_row_id1_into_data overwrites the values ​​remaining only in content_settings. Restoring from a snapshot.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed> $snapshot content_settings before merge
 */
function btb_room_cards_preserve_nonempty_card_fields_from_snapshot(array &$data, array $snapshot): void {
    foreach ($snapshot as $k => $prevVal) {
        if (!is_string($k)) {
            continue;
        }
        if (!preg_match('/^room_(basement|ground_queen|ground_twin|second)_card_(title|description|image_url)$/u', $k)) {
            continue;
        }
        $now = isset($data[$k]) ? trim((string) ($data[$k] ?? '')) : '';
        if ($now !== '') {
            continue;
        }
        $prev = trim((string) ($prevVal ?? ''));
        if ($prev === '') {
            continue;
        }
        $data[$k] = $prevVal;
    }
}

/**
 * Value for the main card: non-empty from room_cards_settings, otherwise from merged $data (after merge + preserve).
 *
 * @param array<string, mixed> $cardRow string room_cards_settings
 * @param array<string, mixed> $merged merged CMS row
 */
function btb_room_card_field_prefer_non_empty(array $cardRow, array $merged, string $key): string {
    if (isset($cardRow[$key]) && trim((string) $cardRow[$key]) !== '') {
        return (string) $cardRow[$key];
    }

    return isset($merged[$key]) ? (string) $merged[$key] : '';
}

/**
 * Legacy single-row table from migrate_page_data.php (room_pages_settings id=1): still holds room_*_gallery copies
 * while runtime canonical reads room_page_settings + content_settings. If those end up empty but legacy has JSON with
 * usable URLs, restore into $data so admin + public pages see galleries again.
 */
function btb_merge_room_pages_settings_legacy_galleries_into_data($conn, array &$data): void {
    if (!$conn || !btb_db_table_exists($conn, 'room_pages_settings') || !function_exists('btb_room_gallery_urls_from_cms_json')) {
        return;
    }
    $r = @$conn->query('SELECT * FROM room_pages_settings WHERE id = 1 LIMIT 1');
    if (!$r || $r->num_rows === 0) {
        return;
    }
    $row = $r->fetch_assoc();
    if (!is_array($row)) {
        return;
    }
    foreach ($row as $col => $legacyVal) {
        if (!is_string($col) || !preg_match('/^room_(basement|ground_queen|ground_twin|second)_(gallery|common_gallery)$/', $col)) {
            continue;
        }
        $legacy = trim((string) ($legacyVal ?? ''));
        if ($legacy === '') {
            continue;
        }
        $legUrls = btb_room_gallery_urls_from_cms_json($legacy);
        if (count($legUrls) === 0) {
            continue;
        }
        $curUrls = btb_room_gallery_urls_from_cms_json($data[$col] ?? '');
        if (count($curUrls) > 0) {
            continue;
        }
        $data[$col] = $legacy;
    }
}

/**
 * When DB has no usable URLs for ground-floor room galleries, rebuild JSON from files still under assets/
 * (upload_image.php names files {imageType}_{time}.ext e.g. room-ground-queen-gallery_1710000000.jpg).
 * Read-only for DB — persists only if the user saves from admin or runs a migration.
 */
function btb_merge_room_ground_galleries_from_assets_if_empty(array &$data): void {
    if (!function_exists('btb_room_gallery_urls_from_cms_json')) {
        return;
    }
    $assetsDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    if (!is_dir($assetsDir) || !is_readable($assetsDir)) {
        return;
    }
    $files = @scandir($assetsDir);
    if (!is_array($files)) {
        return;
    }
    $map = [
        'room_ground_queen_gallery' => 'room-ground-queen-gallery_',
        'room_ground_queen_common_gallery' => 'room-ground-queen-common-gallery_',
        'room_ground_twin_gallery' => 'room-ground-twin-gallery_',
        'room_ground_twin_common_gallery' => 'room-ground-twin-common-gallery_',
    ];
    foreach ($map as $contentKey => $prefix) {
        if (count(btb_room_gallery_urls_from_cms_json($data[$contentKey] ?? '')) > 0) {
            continue;
        }
        $hits = [];
        foreach ($files as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (strpos($name, $prefix) !== 0) {
                continue;
            }
            if (!preg_match('/\.(jpe?g|png|gif)$/i', $name)) {
                continue;
            }
            $full = $assetsDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($full)) {
                continue;
            }
            $hits[] = [
                'url' => 'assets/' . $name,
                'mtime' => @filemtime($full) ?: 0,
            ];
        }
        if ($hits === []) {
            continue;
        }
        usort($hits, static function ($a, $b) {
            return ($b['mtime'] <=> $a['mtime']) ?: strcmp($a['url'], $b['url']);
        });
        $urls = array_column($hits, 'url');
        $data[$contentKey] = json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

/**
 * Read path: overlay all per-section tables onto $data so admin + site use one coherent row (section DB first).
 * Order: explore → rooms/cards/wellness/massage/special/retreat → homepage → floorplan → guest reviews → contact → about → room_page_settings,
 * then explore again so explore_parks_settings / explore_settings win over legacy mirrored columns in about_settings (same as public explore.php).
 */
function btb_merge_phase1_canonical_into_content_row($conn, array &$data): void {
    if (!$conn || !is_array($data)) {
        return;
    }

    // Nightly price columns as loaded from content_settings (before merge mutates $data).
    // Used when BTB_ROOM_PRICE_ROOM_PAGE_SETTINGS_ONLY is on but room_page_settings rows are still empty:
    // otherwise those keys are unset and legacy DB text becomes invisible in admin + on site.
    $roomNightlyPriceSnapshotFromContent = [];
    foreach (btb_room_nightly_price_content_keys() as $nk) {
        if (array_key_exists($nk, $data)) {
            $roomNightlyPriceSnapshotFromContent[$nk] = $data[$nk];
        }
    }

    // Shallow snapshot of content_settings-shaped row before any canonical overlay.
    // Later merges (section tables with id=1) copy every column name; a stray empty homonym must not wipe room_* CMS fields.
    $contentSnapshot = $data;
    if (btb_room_price_room_page_settings_only_active($conn)) {
        foreach (btb_room_nightly_price_content_keys() as $k) {
            unset($contentSnapshot[$k]);
        }
    }
    // Main cards take the price from the detail pages - do not mix the old room_*_card_price from the snapshot.
    foreach (btb_room_card_price_content_keys() as $k) {
        unset($contentSnapshot[$k]);
    }

    btb_merge_explore_canonical_into_content_row($conn, $data);

    btb_merge_cms_table_row_id1_into_data($conn, 'rooms_settings', $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'room_cards_settings', $data);
    btb_room_cards_preserve_nonempty_card_fields_from_snapshot($data, $contentSnapshot);
    foreach (btb_room_card_price_content_keys() as $k) {
        unset($data[$k]);
    }
    btb_merge_cms_table_row_id1_into_data($conn, 'wellness_settings', $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'massage_settings', $data);
    btb_merge_special_settings_into_data($conn, $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'retreat_settings', $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'homepage_settings', $data);
    btb_merge_floorplan_settings_into_data($conn, $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'guest_reviews_settings', $data);
    btb_merge_cms_table_row_id1_into_data($conn, 'contact_settings', $data);
    btb_merge_about_settings_into_data_without_clearing_explore_parks($conn, $data);

    if (btb_room_price_room_page_settings_only_active($conn)) {
        foreach (btb_room_nightly_price_content_keys() as $k) {
            unset($data[$k]);
        }
    }

    if (btb_db_table_exists($conn, 'room_page_settings')) {
        $roomCanon = btb_room_page_settings_column_map();
        $rpsCols = btb_room_page_settings_existing_columns($conn);

        foreach ($roomCanon as $roomKey => $map) {
            $rk = $conn->real_escape_string($roomKey);
            $q = @$conn->query("SELECT * FROM room_page_settings WHERE room_key = '{$rk}' LIMIT 1");
            if (!$q || $q->num_rows === 0) {
                continue;
            }
            $row = $q->fetch_assoc();
            foreach ($map as $col => $contentKey) {
                if (empty($rpsCols[$col]) || !array_key_exists($col, $row)) {
                    continue;
                }
                $v = $row[$col];
                $canonicalEmpty = false;
                if ($v === null) {
                    $canonicalEmpty = true;
                } elseif (is_string($v)) {
                    $t = trim($v);
                    if ($t === '') {
                        $canonicalEmpty = true;
                    } elseif ($col === 'gallery_json' || $col === 'common_gallery_json') {
                        if ($t === '[]' || $t === '{}') {
                            $canonicalEmpty = true;
                        } else {
                            $jd = json_decode($v, true);
                            if (is_array($jd) && count($jd) === 0) {
                                $canonicalEmpty = true;
                            }
                        }
                    }
                }
                if ($canonicalEmpty) {
                    // Placeholder / partial backfill rows must not wipe content still only in content_settings.
                    $existing = isset($data[$contentKey]) ? trim((string) ($data[$contentKey] ?? '')) : '';
                    if ($existing !== '') {
                        continue;
                    }
                    $data[$contentKey] = $v === null ? '' : (string) $v;
                } else {
                    $data[$contentKey] = $v;
                    // room_page_settings row can hold non-empty JSON that yields zero usable image URLs (legacy shapes,
                    // corrupted rows) while content_settings still has the last good export — prefer snapshot for reads.
                    if (($col === 'gallery_json' || $col === 'common_gallery_json') && function_exists('btb_room_gallery_urls_from_cms_json')) {
                        $canUrls = btb_room_gallery_urls_from_cms_json($v);
                        $snapRaw = $contentSnapshot[$contentKey] ?? null;
                        $snapUrls = btb_room_gallery_urls_from_cms_json($snapRaw);
                        if (count($canUrls) === 0 && count($snapUrls) > 0 && is_string($snapRaw) && trim($snapRaw) !== '') {
                            $data[$contentKey] = $snapRaw;
                        }
                    }
                }
            }
        }
    }

    if (function_exists('btb_merge_room_pages_settings_legacy_galleries_into_data')) {
        btb_merge_room_pages_settings_legacy_galleries_into_data($conn, $data);
    }
    if (function_exists('btb_merge_room_ground_galleries_from_assets_if_empty')) {
        btb_merge_room_ground_galleries_from_assets_if_empty($data);
    }

    // about_settings may still hold older copies of about_parks_*; canonical Explore tables must win for admin get_content and all pages.
    if (function_exists('btb_merge_explore_canonical_into_content_row')) {
        btb_merge_explore_canonical_into_content_row($conn, $data);
    }

    btb_restore_empty_room_cms_fields_from_content_snapshot($conn, $data, $contentSnapshot);
    btb_restore_room_nightly_prices_from_content_row_when_canonical_empty($conn, $data, $roomNightlyPriceSnapshotFromContent);
}

/**
 * When nightly rates are canonical in room_page_settings but that row still has no values,
 * fall back to content_settings for read/display only (migration / placeholder INSERTs).
 * If any price field for the room is non-empty after merge, canonical partial data wins (no mixing).
 */
function btb_restore_room_nightly_prices_from_content_row_when_canonical_empty($conn, array &$data, array $priceSnapshotFromContent): void {
    if (!$conn || !btb_room_price_room_page_settings_only_active($conn)) {
        return;
    }
    foreach (btb_room_price_column_map() as $slug => $m) {
        $keys = [$m['prefix'], $m['amount'], $m['suffix'], $m['legacy']];
        $allEmpty = true;
        foreach ($keys as $k) {
            $v = isset($data[$k]) ? trim((string) ($data[$k] ?? '')) : '';
            if ($v !== '') {
                $allEmpty = false;
                break;
            }
        }
        if (!$allEmpty) {
            continue;
        }
        foreach ($keys as $k) {
            if (!array_key_exists($k, $priceSnapshotFromContent)) {
                continue;
            }
            $snap = trim((string) ($priceSnapshotFromContent[$k] ?? ''));
            if ($snap === '') {
                continue;
            }
            $data[$k] = $priceSnapshotFromContent[$k];
        }
    }
}

/**
 * If any room_* field became empty after phase-1 merges but had text in the original content row, restore it.
 * When single-source pricing flags are on, do not restore price keys from stale content_settings snapshot.
 */
function btb_restore_empty_room_cms_fields_from_content_snapshot($conn, array &$data, array $snapshot): void {
    $skip = [];
    if ($conn && btb_room_price_room_page_settings_only_active($conn)) {
        foreach (btb_room_nightly_price_content_keys() as $k) {
            $skip[$k] = true;
        }
    }
    foreach (btb_room_card_price_content_keys() as $k) {
        $skip[$k] = true;
    }
    foreach ($snapshot as $k => $prevVal) {
        if (!is_string($k) || !preg_match('/^room_(basement|second|ground_queen|ground_twin)_[a-z0-9_]+$/', $k)) {
            continue;
        }
        if (!empty($skip[$k])) {
            continue;
        }
        $now = isset($data[$k]) ? trim((string) ($data[$k] ?? '')) : '';
        if ($now !== '') {
            continue;
        }
        $prev = trim((string) ($prevVal ?? ''));
        if ($prev === '') {
            continue;
        }
        $data[$k] = $prevVal;
    }
}

/** @internal Max wellness service cards (Wellness Experiences page). */
function btb_massage_service_cards_max(): int {
    return 20;
}

/** @internal Max price rows per card. */
function btb_massage_service_card_pricing_rows_max(): int {
    return 8;
}

function btb_massage_service_cards_json_column_name(): string {
    return 'massage_service_cards_json';
}

/**
 * Ensure MEDIUMTEXT column on massage_settings for JSON list of service cards.
 */
function btb_ensure_massage_service_cards_json_column($conn): bool {
    if (!$conn || !function_exists('dbTableHasColumn') || !function_exists('btb_db_table_exists')) {
        return false;
    }
    if (!btb_db_table_exists($conn, 'massage_settings')) {
        return false;
    }
    $col = btb_massage_service_cards_json_column_name();
    if (dbTableHasColumn($conn, 'massage_settings', $col)) {
        return true;
    }
    $sql = 'ALTER TABLE massage_settings ADD COLUMN `' . $col . '` MEDIUMTEXT NULL';
    return $conn->query($sql) === true;
}

/**
 * Optional VARCHAR columns for editable booking CTA labels (admin → public pages).
 */
function btb_ensure_booking_button_label_columns($conn): void {
    if (!$conn || !function_exists('btb_db_table_exists') || !function_exists('dbTableHasColumn')) {
        return;
    }
    $defs = [
        ['room_cards_settings', 'homepage_book_a_stay_button_label', 'VARCHAR(191) NULL'],
        ['rooms_settings', 'room_book_now_button_label', 'VARCHAR(191) NULL'],
        ['massage_settings', 'massage_book_service_button_label', 'VARCHAR(191) NULL'],
        ['massage_settings', 'massage_cart_submit_button_label', 'VARCHAR(191) NULL'],
    ];
    foreach ($defs as $row) {
        [$table, $col, $ddl] = $row;
        if (!btb_db_table_exists($conn, $table)) {
            continue;
        }
        if (dbTableHasColumn($conn, $table, $col)) {
            continue;
        }
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        if ($t === '' || $c === '') {
            continue;
        }
        @$conn->query("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$ddl}");
    }
}

if (!function_exists('btb_default_massage_pricing_map')) {
    /**
     * @return array<string, list<array{duration:int,label:string,price:string}>>
     */
    function btb_default_massage_pricing_map() {
        return [
            'relaxing' => [
                ['duration' => 60, 'timeAmount' => 60, 'timeUnit' => 'minutes', 'label' => '60 minutes', 'priceAmount' => '110', 'priceCurrency' => 'CAD', 'price' => '110 CAD'],
                ['duration' => 90, 'timeAmount' => 90, 'timeUnit' => 'minutes', 'label' => '90 minutes', 'priceAmount' => '160', 'priceCurrency' => 'CAD', 'price' => '160 CAD'],
            ],
            'deep_tissue' => [
                ['duration' => 60, 'timeAmount' => 60, 'timeUnit' => 'minutes', 'label' => '60 minutes', 'priceAmount' => '120', 'priceCurrency' => 'CAD', 'price' => '120 CAD'],
                ['duration' => 90, 'timeAmount' => 90, 'timeUnit' => 'minutes', 'label' => '90 minutes', 'priceAmount' => '170', 'priceCurrency' => 'CAD', 'price' => '170 CAD'],
            ],
            'reiki' => [
                ['duration' => 15, 'timeAmount' => 15, 'timeUnit' => 'minutes', 'label' => '15 minutes', 'priceAmount' => '25', 'priceCurrency' => 'CAD', 'price' => '25 CAD'],
                ['duration' => 30, 'timeAmount' => 30, 'timeUnit' => 'minutes', 'label' => '30 minutes', 'priceAmount' => '50', 'priceCurrency' => 'CAD', 'price' => '50 CAD'],
            ],
            'sauna' => [
                ['duration' => 60, 'timeAmount' => 1, 'timeUnit' => 'hour', 'label' => '1 hour', 'priceAmount' => '25', 'priceCurrency' => 'CAD', 'price' => '25 CAD'],
            ],
        ];
    }

    /**
     * Merge stored JSON lines with fixed-duration defaults (legacy four service types).
     *
     * @return list<array{duration:int,label:string,price:string}>
     */
    function btb_parse_massage_pricing($jsonOrNull, $key) {
        $defaults = btb_default_massage_pricing_map();
        $default = $defaults[$key] ?? [];
        $decoded = json_decode((string) ($jsonOrNull ?? ''), true);
        if (!is_array($decoded) || $decoded === []) {
            return $default;
        }
        $byDur = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['duration'])) {
                $byDur[(int) $row['duration']] = $row;
            }
        }
        $out = [];
        foreach ($default as $rowDef) {
            $dur = (int) ($rowDef['duration'] ?? 0);
            if (isset($byDur[$dur]) && is_array($byDur[$dur])) {
                $r = $byDur[$dur];
                $label = trim((string) ($r['label'] ?? $rowDef['label']));
                $price = trim((string) ($r['price'] ?? $rowDef['price']));
                if ($label === '') {
                    $label = $rowDef['label'];
                }
                if ($price === '') {
                    $price = $rowDef['price'];
                }
                $base = ['duration' => $dur, 'label' => $label, 'price' => $price];
                $src = array_merge($rowDef, $r);
                $out[] = btb_massage_pricing_row_merge_parts($base, $src);
            } else {
                $out[] = btb_massage_pricing_row_merge_parts(
                    [
                        'duration' => $dur,
                        'label' => (string) ($rowDef['label'] ?? ''),
                        'price' => (string) ($rowDef['price'] ?? ''),
                    ],
                    $rowDef
                );
            }
        }
        return $out;
    }
}

if (!function_exists('btb_massage_duration_minutes_from_parts')) {
    function btb_massage_duration_minutes_from_parts(int $amount, string $unitRaw): int {
        if ($amount < 1) {
            return 0;
        }
        $u = strtolower(trim($unitRaw));
        if (preg_match('/^(hours?|hrs?|h)$/', $u) === 1) {
            return (int) round($amount * 60);
        }
        if (preg_match('/^(minutes?|mins?|m)$/', $u) === 1) {
            return $amount;
        }
        if (strpos($u, 'hour') !== false) {
            return (int) round($amount * 60);
        }
        if (strpos($u, 'min') !== false) {
            return $amount;
        }
        return $amount;
    }
}

if (!function_exists('btb_massage_pricing_row_merge_parts')) {
    /**
     * Keep combined `price` in sync with optional priceAmount + priceCurrency; preserve admin fields.
     *
     * @param array<string, mixed> $base duration, label, price
     * @param array<string, mixed> $src original row (defaults or stored)
     * @return array<string, mixed>
     */
    function btb_massage_pricing_row_merge_parts(array $base, array $src): array {
        $price = trim((string) ($base['price'] ?? ''));
        if ($price === '') {
            $pAmt = trim((string) ($src['priceAmount'] ?? ''));
            $pCur = trim((string) ($src['priceCurrency'] ?? ''));
            if ($pAmt !== '') {
                $price = trim($pAmt . ' ' . ($pCur !== '' ? $pCur : 'CAD'));
            }
        }
        $base['price'] = $price;
        foreach (['timeAmount', 'timeUnit', 'priceAmount', 'priceCurrency'] as $k) {
            if (array_key_exists($k, $src)) {
                $base[$k] = $src[$k];
            }
        }
        return $base;
    }
}

/**
 * Parse wellness pricing JSON without preset defaults (custom cards).
 *
 * @return list<array<string, mixed>>
 */
function btb_parse_massage_pricing_freeform($jsonOrNull): array {
    $decoded = json_decode((string) ($jsonOrNull ?? ''), true);
    if (!is_array($decoded) || $decoded === []) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $dur = isset($row['duration']) ? (int) $row['duration'] : 0;
        if ($dur < 1 && isset($row['timeAmount'], $row['timeUnit'])) {
            $dur = btb_massage_duration_minutes_from_parts((int) $row['timeAmount'], (string) $row['timeUnit']);
        }
        if ($dur < 1 || $dur > 999) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '' && isset($row['timeAmount'], $row['timeUnit'])) {
            $label = trim((string) $row['timeAmount'] . ' ' . (string) $row['timeUnit']);
        }
        $price = trim((string) ($row['price'] ?? ''));
        $base = ['duration' => $dur, 'label' => $label, 'price' => $price];
        $out[] = btb_massage_pricing_row_merge_parts($base, $row);
        if (count($out) >= btb_massage_service_card_pricing_rows_max()) {
            break;
        }
    }
    usort($out, static function ($a, $b) {
        return ($a['duration'] ?? 0) <=> ($b['duration'] ?? 0);
    });
    return $out;
}

/**
 * Default four cards from legacy CMS columns (when JSON column empty or invalid).
 *
 * @param array<string, mixed> $data merged content row
 * @return list<array<string, mixed>>
 */
function btb_massage_service_cards_legacy_from_content(array $data): array {
    $pick = static function (array $d, string $key, string $fallback): string {
        $v = trim((string) ($d[$key] ?? ''));
        return $v !== '' ? $v : $fallback;
    };

    $rows = [
        [
            'id' => 'legacy-relaxing',
            'bookingTitle' => 'Relaxing Massage',
            'title' => $pick($data, 'massage_relaxing_title', 'Relaxing Massage'),
            'description' => (string) ($data['massage_relaxing_description'] ?? ''),
            'imageUrl' => trim((string) ($data['massage_relaxing_image_url'] ?? '')),
            'pricing' => btb_parse_massage_pricing($data['massage_pricing_relaxing'] ?? null, 'relaxing'),
            'pricingPreset' => 'relaxing',
        ],
        [
            'id' => 'legacy-deep-tissue',
            'bookingTitle' => 'Deep Tissue Massage',
            'title' => $pick($data, 'massage_deep_tissue_title', 'Deep Tissue Massage'),
            'description' => (string) ($data['massage_deep_tissue_description'] ?? ''),
            'imageUrl' => trim((string) ($data['massage_deep_tissue_image_url'] ?? '')),
            'pricing' => btb_parse_massage_pricing($data['massage_pricing_deep_tissue'] ?? null, 'deep_tissue'),
            'pricingPreset' => 'deep_tissue',
        ],
        [
            'id' => 'legacy-reiki',
            'bookingTitle' => 'Reiki Energy Healing',
            'title' => $pick($data, 'massage_reiki_title', 'Reiki Energy Healing'),
            'description' => (string) ($data['massage_reiki_description'] ?? ''),
            'imageUrl' => trim((string) ($data['massage_reiki_image_url'] ?? '')),
            'pricing' => btb_parse_massage_pricing($data['massage_pricing_reiki'] ?? null, 'reiki'),
            'pricingPreset' => 'reiki',
        ],
        [
            'id' => 'legacy-sauna',
            'bookingTitle' => 'Sauna',
            'title' => $pick($data, 'massage_sauna_title', 'Sauna'),
            'description' => (string) ($data['massage_sauna_description'] ?? ''),
            'imageUrl' => trim((string) ($data['massage_sauna_image_url'] ?? '')),
            'pricing' => btb_parse_massage_pricing($data['massage_pricing_sauna'] ?? null, 'sauna'),
            'pricingPreset' => 'sauna',
        ],
    ];

    return $rows;
}

/**
 * @param array<string, mixed> $row one decoded card object
 * @return array<string, mixed>
 */
function btb_massage_service_card_normalize_assoc(array $row): array {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($row['id'] ?? ''));
    if ($id === '' || strlen($id) > 64) {
        $id = 'card-' . substr(sha1(json_encode($row) . random_bytes(4)), 0, 10);
    }
    $title = trim((string) ($row['title'] ?? ''));
    $bookingTitle = trim((string) ($row['bookingTitle'] ?? ''));
    if ($bookingTitle === '') {
        $bookingTitle = $title;
    }
    if ($title === '') {
        $title = $bookingTitle !== '' ? $bookingTitle : 'Service';
    }
    if ($bookingTitle === '') {
        $bookingTitle = $title;
    }
    $description = (string) ($row['description'] ?? '');
    $imageUrl = trim((string) ($row['imageUrl'] ?? ''));
    $preset = trim((string) ($row['pricingPreset'] ?? ''));
    $allowedPreset = ['relaxing', 'deep_tissue', 'reiki', 'sauna'];
    $pricingPreset = in_array($preset, $allowedPreset, true) ? $preset : '';

    $pricingRaw = $row['pricing'] ?? null;
    $pricingJson = '';
    if (is_array($pricingRaw)) {
        $pricingJson = json_encode($pricingRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_string($pricingRaw)) {
        $pricingJson = trim($pricingRaw);
    }

    if ($pricingPreset !== '' && function_exists('btb_parse_massage_pricing')) {
        $pricing = btb_parse_massage_pricing($pricingJson !== '' ? $pricingJson : null, $pricingPreset);
    } else {
        $pricing = btb_parse_massage_pricing_freeform($pricingJson !== '' ? $pricingJson : '[]');
    }

    $out = [
        'id' => $id,
        'bookingTitle' => $bookingTitle,
        'title' => $title,
        'description' => $description,
        'imageUrl' => $imageUrl,
        'pricing' => $pricing,
    ];
    if ($pricingPreset !== '') {
        $out['pricingPreset'] = $pricingPreset;
    }
    return $out;
}

/**
 * Normalize stored JSON for massage_service_cards_json (POST save).
 */
function btb_massage_service_cards_normalize_json_string(string $raw): string {
    $trim = trim($raw);
    if ($trim === '') {
        return '[]';
    }
    $dec = json_decode($trim, true);
    if (!is_array($dec)) {
        return '[]';
    }
    $max = btb_massage_service_cards_max();
    $out = [];
    foreach ($dec as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = btb_massage_service_card_normalize_assoc($row);
        if (count($out) >= $max) {
            break;
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Effective wellness cards for SSR / API (legacy columns if JSON unset).
 *
 * @param array<string, mixed> $data merged content row
 * @return list<array<string, mixed>>
 */
function btb_massage_service_cards_effective_list(array $data): array {
    $key = btb_massage_service_cards_json_column_name();
    $raw = trim((string) ($data[$key] ?? ''));
    if ($raw === '') {
        return btb_massage_service_cards_legacy_from_content($data);
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return btb_massage_service_cards_legacy_from_content($data);
    }
    if ($dec === []) {
        return [];
    }
    $out = [];
    foreach ($dec as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = btb_massage_service_card_normalize_assoc($row);
        if (count($out) >= btb_massage_service_cards_max()) {
            break;
        }
    }
    return $out;
}

/**
 * POST keys that may be stored on massage_settings (columns must exist on server).
 */
function btb_cms_massage_dual_write_post_keys(): array {
    return [
        'massage_hero_title', 'massage_hero_image_url', 'massage_intro',
        'massage_relaxing_title', 'massage_relaxing_description',
        'massage_deep_tissue_title', 'massage_deep_tissue_description',
        'massage_reiki_title', 'massage_reiki_description',
        'massage_sauna_title', 'massage_sauna_description',
        'massage_booking_title', 'massage_booking_intro',
        'massage_book_service_button_label',
        'massage_cart_submit_button_label',
        'massage_pricing_relaxing', 'massage_pricing_deep_tissue', 'massage_pricing_reiki', 'massage_pricing_sauna',
        'massage_relaxing_image_url', 'massage_deep_tissue_image_url', 'massage_reiki_image_url', 'massage_sauna_image_url',
        'wellness_stay_gallery_overlay',
        btb_massage_service_cards_json_column_name(),
    ];
}

function btb_cms_special_dual_write_post_keys(): array {
    return array_merge([
        'special_hero_title', 'special_hero_subtitle',
        'special_pools_title', 'special_pools_description_1', 'special_pools_description_2',
        'special_dining_title', 'special_dining_description_1',
        'special_extra_title', 'special_extra_description_1', 'special_extra_description_2',
        'special_offer_title', 'special_offer_main_text', 'special_offer_rooms_cta_label',
        'special_hero_image_url', 'special_pools_image_url', 'special_dining_image_url', 'special_extra_image_url',
        btb_special_addon_panels_json_column_name(),
    ], array_keys(btb_special_block2_column_sql_definitions()));
}

function btb_cms_retreat_dual_write_post_keys(): array {
    return [
        'retreat_hero_title', 'retreat_hero_subtitle', 'retreat_intro_text',
        'retreat_locations_title', 'retreat_forest_title', 'retreat_forest_description',
        'retreat_forest_list_label', 'retreat_forest_list_items', 'retreat_forest_gallery', 'retreat_indoor_title',
        'retreat_indoor_description', 'retreat_indoor_additional', 'retreat_indoor_gallery', 'retreat_theatre_title',
        'retreat_theatre_description', 'retreat_theatre_gallery', 'retreat_contact_title', 'retreat_contact_text',
        'retreat_organizer_title',
        'retreat_workshops_title', 'retreat_workshops_intro',
        'retreat_workshops_list', 'retreat_workshops_conclusion', 'retreat_collaboration_title',
        'retreat_collaboration_intro', 'retreat_collaboration_list', 'retreat_collaboration_conclusion',
        'retreat_hero_image_url', 'retreat_forest_image_url', 'retreat_indoor_image_url', 'retreat_theatre_image_url',
        'retreat_collaboration_image_url',
        'retreat_gallery_overlay_forest', 'retreat_gallery_overlay_indoor', 'retreat_gallery_overlay_theatre',
    ];
}

function btb_cms_wellness_dual_write_post_keys(): array {
    return [
        'wellness_title', 'wellness_description',
        'wellness_massage_title', 'wellness_massage_description',
        'wellness_yoga_title', 'wellness_yoga_description',
        'wellness_massage_image_url', 'wellness_yoga_image_url', 'wellness_sauna_image_url',
    ];
}

function btb_cms_about_dual_write_post_keys(): array {
    return [
        'about_hero_title', 'about_hero_subtitle',
        'about_idea_title', 'about_idea_intro', 'about_idea_paragraph_1', 'about_idea_paragraph_2',
        'about_idea_paragraph_3', 'about_idea_signature',
        'about_location_title', 'about_location_paragraph_1', 'about_location_paragraph_2',
        'about_location_paragraph_3', 'about_location_paragraph_4', 'about_location_coordinates',
        'about_location_deer_warning',
        'about_contact_form_title', 'about_contact_form_description',
        'about_attractions_title', 'about_attractions_lead',
        'about_procter_title', 'about_procter_distance', 'about_procter_description',
        'about_halcyon_title', 'about_halcyon_distance', 'about_halcyon_description',
        'about_whitewater_title', 'about_whitewater_distance', 'about_whitewater_description',
        'about_nelson_title', 'about_nelson_distance', 'about_nelson_description',
        'about_parks_title', 'about_parks_intro', 'about_parks_list',
        'about_hero_image_url', 'about_founder_image_url', 'about_procter_image_url', 'about_nelson_image_url',
        'about_procter_gallery', 'about_halcyon_gallery', 'about_whitewater_gallery', 'about_nelson_gallery',
        'about_kaslo_title', 'about_kaslo_distance', 'about_kaslo_description', 'about_kaslo_image_url', 'about_kaslo_gallery',
        'about_crawford_title', 'about_crawford_distance', 'about_crawford_description', 'about_crawford_image_url', 'about_crawford_gallery',
        'about_museum_title', 'about_museum_distance', 'about_museum_description', 'about_museum_image_url', 'about_museum_gallery',
    ];
}

function btb_cms_room_cards_dual_write_post_keys(): array {
    return [
        'room_basement_card_title', 'room_basement_card_description',
        'room_basement_card_image_url',
        'room_ground_queen_card_title', 'room_ground_queen_card_description',
        'room_ground_queen_card_image_url',
        'room_ground_twin_card_title', 'room_ground_twin_card_description',
        'room_ground_twin_card_image_url',
        'room_second_card_title', 'room_second_card_description',
        'room_second_card_image_url',
        'homepage_book_a_stay_button_label',
        'mini_hotel_title', 'mini_hotel_description', 'mini_hotel_image_url',
        'mini_hotel_description_1', 'mini_hotel_description_2',
    ];
}

/**
 * UPDATE table SET ... WHERE id=1 for POST keys that exist as columns (INSERT row if missing).
 */
function btb_dual_write_post_keys_to_table_id1($conn, string $table, array $postKeyCandidates): void {
    if (!$conn || !isset($_POST) || !is_array($_POST)) {
        return;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($t === '' || !btb_db_table_exists($conn, $t)) {
        return;
    }
    static $colCache = [];
    if (!isset($colCache[$t])) {
        $colCache[$t] = [];
        $res = @$conn->query("SHOW COLUMNS FROM `{$t}`");
        if ($res) {
            while ($x = $res->fetch_assoc()) {
                $colCache[$t][$x['Field']] = true;
            }
        }
    }
    $upd = [];
    foreach ($postKeyCandidates as $pk) {
        if (!array_key_exists($pk, $_POST)) {
            continue;
        }
        if (!isset($colCache[$t][$pk])) {
            continue;
        }
        $upd[$pk] = $_POST[$pk];
    }
    if (empty($upd)) {
        return;
    }
    @$conn->query("INSERT IGNORE INTO `{$t}` (id) VALUES (1)");
    $sets = [];
    $vals = [];
    $types = '';
    foreach ($upd as $k => $v) {
        $kSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
        if ($kSafe === '') {
            continue;
        }
        $sets[] = "`{$kSafe}` = ?";
        $vals[] = (string) ($v ?? '');
        $types .= 's';
    }
    if (empty($sets)) {
        return;
    }
    $sql = "UPDATE `{$t}` SET " . implode(', ', $sets) . ' WHERE id = 1';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * CMS canonical storage (single write target per field group).
 *
 * Read path: api get_content loads content_settings id=1, then btb_merge_phase1_canonical_into_content_row()
 * overlays rows from these tables so the admin UI sees one merged document.
 *
 * Write path: save_content in api.php persists all other section tables (including homepage_settings,
 * contact_settings, about_settings, massage_settings, wellness_settings, room_page_* POST columns, etc.).
 * This tail call only persists room_page_settings rows (per room_key) from POST — the only table not fully
 * covered inline in api.php save_content.
 */
function btb_dual_write_phase1_canonical_from_post($conn): void {
    if (!$conn || !isset($_POST) || !is_array($_POST)) {
        return;
    }

    if (!btb_db_table_exists($conn, 'room_page_settings')) {
        return;
    }

    $roomCanon = btb_room_page_settings_column_map();
    $rpsCols = btb_room_page_settings_existing_columns($conn);

    foreach ($roomCanon as $roomKey => $map) {
        $upd = [];
        foreach ($map as $col => $postKey) {
            if (empty($rpsCols[$col]) || !array_key_exists($postKey, $_POST)) {
                continue;
            }
            $upd[$col] = $_POST[$postKey];
        }
        if (empty($upd)) {
            continue;
        }
        $rkEsc = $conn->real_escape_string($roomKey);
        @$conn->query("INSERT IGNORE INTO room_page_settings (room_key) VALUES ('{$rkEsc}')");
        $sets = [];
        $vals = [];
        $types = '';
        foreach ($upd as $col => $v) {
            $c = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if ($c === '') {
                continue;
            }
            $sets[] = "`{$c}` = ?";
            $vals[] = (string) ($v ?? '');
            $types .= 's';
        }
        if (empty($sets)) {
            continue;
        }
        $vals[] = $roomKey;
        $types .= 's';
        $sql = 'UPDATE room_page_settings SET ' . implode(', ', $sets) . ' WHERE room_key = ?';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function insertRecord($conn, $table, $data) {
    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = executeQuery($conn, $sql, array_values($data));
        if (!$stmt) {
            return false;
        }
        
        $insertId = $conn->insert_id;
        $stmt->close();
        
        return $insertId;
    } catch (Exception $e) {
        error_log("Insert error: " . $e->getMessage());
        return false;
    }
}

function updateRecord($conn, $table, $data, $where, $whereParams = []) {
    try {
        $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = executeQuery($conn, $sql, $params);
        
        if (!$stmt) {
            return false;
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage());
        return false;
    }
}

function deleteRecord($conn, $table, $where, $params = []) {
    try {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $stmt = executeQuery($conn, $sql, $params);
        if (!$stmt) {
            return false;
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        return false;
    }
}

// Validation utilities
function validateRequired($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "Field '{$field}' is required";
        }
    }
    
    return $errors;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    $phone = trim((string) $phone);
    if ($phone === '') {
        return false;
    }
    // Count digits only so unicode dashes/spaces (e.g. U+2011 in “555‑1234”) still validate.
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen($digits) >= 10;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// File utilities
function uploadFile($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    try {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }
        
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        if ($fileSize > btb_upload_image_max_file_bytes()) {
            throw new Exception('File too large');
        }
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        // Generate unique filename
        $newFileName = uniqid() . '.' . $fileExt;
        $uploadPath = $uploadDir . '/' . $newFileName;
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($fileTmpName, $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return $newFileName;
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return false;
    }
}

function deleteFile($filePath) {
    try {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    } catch (Exception $e) {
        error_log("File delete error: " . $e->getMessage());
        return false;
    }
}

// API utilities
function getApiAction() {
    return $_POST['action'] ?? $_GET['action'] ?? '';
}

function getApiData() {
    $input = file_get_contents('php://input');
    if ($input) {
        return json_decode($input, true);
    }
    return $_POST;
}

function requireApiAction($action) {
    if (getApiAction() !== $action) {
        sendError('Invalid action');
    }
}

function requireAuth() {
    // Simple auth check - can be enhanced
    $authToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authToken)) {
        sendError('Authentication required');
    }
}

// Logging utilities
function logActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    $logFile = 'logs/activity.log';
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Common table operations
function getTableData($conn, $table, $where = '', $params = []) {
    $sql = "SELECT * FROM {$table}";
    if (!empty($where)) {
        $sql .= " WHERE {$where}";
    }
    $sql .= " ORDER BY created_at DESC";
    
    return fetchAll($conn, $sql, $params);
}

function getTableCount($conn, $table, $where = '', $params = []) {
    $sql = "SELECT COUNT(*) as count FROM {$table}";
    if (!empty($where)) {
        $sql .= " WHERE {$where}";
    }
    
    $result = fetchOne($conn, $sql, $params);
    return $result ? $result['count'] : 0;
}

// Content management utilities
function getContentSetting($conn, $key, $default = '') {
    $sql = "SELECT {$key} FROM content_settings WHERE id = 1";
    $result = fetchOne($conn, $sql);
    
    return $result ? $result[$key] : $default;
}

function updateContentSetting($conn, $key, $value) {
    $sql = "UPDATE content_settings SET {$key} = ? WHERE id = 1";
    return executeQuery($conn, $sql, [$value]);
}

/**
 * Load exported DB defaults bundle from database/snapshots/default_texts_from_db.php.
 * This file is optional and read-only: if missing/invalid, we silently fall back to code defaults.
 *
 * @return array<string, mixed>
 */
function btb_defaults_bundle() {
    static $bundle = null;
    if ($bundle !== null) {
        return $bundle;
    }
    $bundle = [];
    $path = __DIR__ . '/database/snapshots/default_texts_from_db.php';
    if (!is_file($path)) {
        return $bundle;
    }
    $loaded = require $path;
    if (is_array($loaded)) {
        $bundle = $loaded;
    }
    return $bundle;
}

/**
 * Return default text by exported bundle key, else provided fallback.
 */
function btb_default_text($bundleKey, $fallback = '') {
    $bundle = btb_defaults_bundle();
    if (array_key_exists($bundleKey, $bundle)) {
        $v = $bundle[$bundleKey];
        if ($v !== null) {
            $s = trim((string) $v);
            // Snapshot may contain intentionally empty keys; do not let them wipe code fallbacks.
            if ($s !== '') {
                return (string) $v;
            }
        }
    }
    return $fallback;
}

/**
 * For text fields coming from DB row arrays:
 * - if field exists and is non-empty => use actual DB value
 * - else => use exported default bundle key, then fallback string
 */
function btb_field_or_default(array $row, $field, $bundleKey, $fallback = '') {
    if (array_key_exists($field, $row) && trim((string) $row[$field]) !== '') {
        return (string) $row[$field];
    }
    return btb_default_text($bundleKey, $fallback);
}

/**
 * Normalize Specials card body text for overlap checks (admin splits into two DB fields).
 */
function btb_special_plain_for_dedupe(string $s): string {
    $t = str_replace(["\r\n", "\r", "\n"], ' ', $s);
    $t = preg_replace('/<br\s*\/?>/i', ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', strip_tags($t)));
}

/**
 * If the second paragraph is fully contained in the first (legacy bad saves), drop the second
 * so the public page does not render duplicate copy.
 *
 * @return array{0: string, 1: string}
 */
function btb_special_dedupe_description_pair(string $a, string $b): array {
    $plainB = btb_special_plain_for_dedupe($b);
    if ($plainB === '') {
        return [$a, $b];
    }
    $plainA = btb_special_plain_for_dedupe($a);
    if ($plainA !== '' && str_contains($plainA, $plainB)) {
        return [$a, ''];
    }
    return [$a, $b];
}

/**
 * Two-paragraph Specials card fields: if only the first DB column is filled, do not fall back to the
 * bundled default for the second — otherwise the site shows a paragraph that never appears as a
 * separate control in the admin (single combined preview).
 *
 * @return array{0: string, 1: string}
 */
function btb_special_twin_description_fields_from_row(
    array $row,
    string $field1,
    string $bundle1,
    string $fallback1,
    string $field2,
    string $bundle2,
    string $fallback2
): array {
    $has1 = array_key_exists($field1, $row) && trim((string) $row[$field1]) !== '';
    $has2 = array_key_exists($field2, $row) && trim((string) $row[$field2]) !== '';

    if (!$has1 && !$has2) {
        return [
            btb_default_text($bundle1, $fallback1),
            btb_default_text($bundle2, $fallback2),
        ];
    }
    if ($has1 && !$has2) {
        return [(string) $row[$field1], ''];
    }
    if (!$has1 && $has2) {
        return [
            btb_default_text($bundle1, $fallback1),
            (string) $row[$field2],
        ];
    }

    return [(string) $row[$field1], (string) $row[$field2]];
}

/** Safe single-line / attribute text */
function safeOutput($value, $fallback = '') {
    return htmlspecialchars($value ?? $fallback, ENT_QUOTES, 'UTF-8');
}

/**
 * One gallery entry from JSON (string path/URL or legacy object shape).
 */
function btb_room_gallery_item_to_url_string($item): string {
    if (is_string($item)) {
        return trim($item);
    }
    if (is_array($item)) {
        foreach (['url', 'src', 'imageUrl', 'image_url', 'path', 'href'] as $k) {
            if (isset($item[$k]) && is_string($item[$k])) {
                $s = trim($item[$k]);
                if ($s !== '') {
                    return $s;
                }
            }
        }
    }

    return '';
}

/**
 * Room / JSON gallery arrays: return ordered non-empty URL strings (same rules as front-end filters).
 *
 * @param array<int|string, mixed> $gallery
 * @return list<string>
 */
function btb_room_gallery_valid_urls(array $gallery): array {
    $out = [];
    foreach ($gallery as $u) {
        $s = btb_room_gallery_item_to_url_string($u);
        if ($s !== '') {
            $out[] = $s;
        }
    }

    return $out;
}

/**
 * Decode CMS gallery JSON (possibly double-encoded) into URL strings for public room pages.
 *
 * @param mixed $galleryJson
 * @return list<string>
 */
function btb_room_gallery_urls_from_cms_json($galleryJson): array {
    $raw = is_string($galleryJson) ? trim($galleryJson) : '';
    if ($raw === '') {
        return [];
    }
    for ($pass = 0; $pass < 4; $pass++) {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            return btb_room_gallery_valid_urls($dec);
        }
        if (is_string($dec)) {
            $next = trim($dec);
            if ($next === '' || $next === $raw) {
                break;
            }
            $raw = $next;
            continue;
        }
        break;
    }

    return [];
}

/** Max thumbnails in room/common gallery grid (desktop). */
function btb_room_gallery_desktop_preview_limit(): int {
    return 5;
}

/** Max thumbnails in room/common gallery grid (mobile). */
function btb_room_gallery_mobile_preview_limit(): int {
    return 3;
}

/**
 * URLs shown in the public gallery grid (up to 5; extra tiles hidden on mobile via CSS).
 *
 * @param list<string> $urls
 * @return list<string>
 */
function btb_room_gallery_grid_preview_urls(array $urls): array {
    $total = count($urls);
    if ($total === 0) {
        return [];
    }
    $limit = min(btb_room_gallery_desktop_preview_limit(), $total);

    return array_slice($urls, 0, $limit);
}

/** CSS class for preview thumb: hide 4th–5th image on mobile (desktop shows up to 5). */
function btb_room_gallery_thumb_class(int $index): string {
    return $index >= btb_room_gallery_mobile_preview_limit() ? 'gallery-tile--desktop-only' : '';
}

/** Whether to show the "View all N photos" tile in the grid. */
function btb_room_gallery_show_view_all(int $total): bool {
    return $total > btb_room_gallery_mobile_preview_limit();
}

/** CSS classes for view-all tile (mobile-only when 4–5 photos; both when >5). */
function btb_room_gallery_view_all_tile_class(int $total): string {
    $classes = 'gallery-view-all-tile';
    if ($total > btb_room_gallery_mobile_preview_limit() && $total <= btb_room_gallery_desktop_preview_limit()) {
        $classes .= ' gallery-tile--mobile-only';
    }

    return $classes;
}

/**
 * Render multi-line CMS text: escapes HTML; newlines → <br>; each line starting with "-" (after optional spaces) becomes a list item.
 */
function safeOutputWithBreaks($value, $fallback = '') {
    $text = $value ?? $fallback;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $runs = [];
    $bufText = [];
    $bufList = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*-\s*(.*)$/u', $line, $m)) {
            if ($bufText !== []) {
                $runs[] = ['t', $bufText];
                $bufText = [];
            }
            $bufList[] = $m[1];
        } else {
            if ($bufList !== []) {
                $runs[] = ['l', $bufList];
                $bufList = [];
            }
            $bufText[] = $line;
        }
    }
    if ($bufText !== []) {
        $runs[] = ['t', $bufText];
    }
    if ($bufList !== []) {
        $runs[] = ['l', $bufList];
    }

    $html = '';
    foreach ($runs as $run) {
        if ($run[0] === 't') {
            $html .= nl2br(htmlspecialchars(implode("\n", $run[1]), ENT_QUOTES, 'UTF-8'));
        } else {
            $html .= '<ul class="desc-bullets">';
            foreach ($run[1] as $item) {
                $html .= '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }
    }
    return $html;
}

/**
 * First numeric amount in CMS price text (HTML stripped), e.g. "140", "<strong>140</strong> CAD".
 * @param mixed $raw
 * @return float|null
 */
function btb_extract_first_price_number($raw) {
    if ($raw === null) {
        return null;
    }
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(["\r\n", "\r"], "\n", (string) $raw))));
    if ($plain === '') {
        return null;
    }
    if (preg_match('/(\d+(?:\.\d+)?)/u', $plain, $m)) {
        return floatval($m[1]);
    }
    return null;
}

/**
 * Best-effort nightly rate from a room price line (legacy HTML or plain).
 * Avoids using the first digit group in the string — phrases like "2 guests" or "up to 4 people"
 * used to yield 2 or 4 instead of the real CAD amount when inferring split fields.
 *
 * @param mixed $raw
 * @return float|null
 */
function btb_extract_room_nightly_rate_number($raw) {
    if ($raw === null) {
        return null;
    }
    $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(["\r\n", "\r"], "\n", (string) $raw))));
    if ($plain === '') {
        return null;
    }
    if (!preg_match_all('/\d+(?:\.\d+)?/u', $plain, $matches)) {
        return btb_extract_first_price_number($raw);
    }
    $best = null;
    foreach ($matches[0] as $s) {
        $n = floatval($s);
        if ($n >= 1900 && $n <= 2100) {
            continue;
        }
        // Guest counts are often 1–4; nightly rates in this CMS are typically ≥ 20.
        if ($n >= 20 && $n <= 99999) {
            if ($best === null || $n > $best) {
                $best = $n;
            }
        }
    }
    if ($best !== null) {
        return $best;
    }

    return btb_extract_first_price_number($raw);
}

/** @return array<string, array{prefix:string,amount:string,suffix:string,legacy:string}> */
function btb_room_price_column_map() {
    return [
        'basement' => [
            'prefix' => 'room_basement_price_prefix',
            'amount' => 'room_basement_price_amount',
            'suffix' => 'room_basement_price_suffix',
            'legacy' => 'room_basement_price',
        ],
        'ground_queen' => [
            'prefix' => 'room_ground_queen_price_prefix',
            'amount' => 'room_ground_queen_price_amount',
            'suffix' => 'room_ground_queen_price_suffix',
            'legacy' => 'room_ground_queen_price',
        ],
        'ground_twin' => [
            'prefix' => 'room_ground_twin_price_prefix',
            'amount' => 'room_ground_twin_price_amount',
            'suffix' => 'room_ground_twin_price_suffix',
            'legacy' => 'room_ground_twin_price',
        ],
        'second' => [
            'prefix' => 'room_second_price_prefix',
            'amount' => 'room_second_price_amount',
            'suffix' => 'room_second_price_suffix',
            'legacy' => 'room_second_price',
        ],
    ];
}

function btb_room_price_allowed_tags() {
    return '<strong><em><b><i><br>';
}

/**
 * Plain text for admin inputs and for composing the public line (no HTML in stored prefix/suffix).
 */
function btb_room_price_input_plain($value) {
    if ($value === null) {
        return '';
    }
    $s = (string) $value;
    if ($s === '') {
        return '';
    }
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strip_tags($s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return $s;
}

/**
 * @return array{prefix:string,amount:string,suffix:string}
 */
function btb_room_price_default_triplet($slug) {
    $defaults = [
        'basement' => ['prefix' => 'Price:', 'amount' => '140', 'suffix' => 'CAD / night'],
        'ground_queen' => ['prefix' => 'Price:', 'amount' => '130', 'suffix' => 'CAD / night'],
        'ground_twin' => ['prefix' => 'Price:', 'amount' => '125', 'suffix' => 'CAD / night'],
        'second' => ['prefix' => 'Price:', 'amount' => '210', 'suffix' => 'CAD / night (entire floor)'],
    ];
    return $defaults[$slug] ?? ['prefix' => 'Price:', 'amount' => '', 'suffix' => 'CAD / night'];
}

function btb_room_price_default_line_html($slug) {
    $t = btb_room_price_default_triplet($slug);
    return btb_room_price_line_from_parts_string($t['prefix'], $t['amount'], $t['suffix']);
}

/**
 * @return array{prefix:string,amount:string,suffix:string}
 */
function btb_room_price_infer_from_legacy($legacyHtml, $slug) {
    $legacy = trim((string) $legacyHtml);
    if ($legacy === '') {
        return ['prefix' => '', 'amount' => '', 'suffix' => ''];
    }
    $n = btb_extract_room_nightly_rate_number($legacy);
    if ($n === null || $n <= 0) {
        return ['prefix' => '', 'amount' => '', 'suffix' => ''];
    }
    $disp = (abs($n - round($n)) < 0.001)
        ? (string) (int) round($n)
        : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    $suffix = (stripos($legacy, 'entire floor') !== false) ? 'CAD / night (entire floor)' : 'CAD / night';
    return ['prefix' => 'Price:', 'amount' => $disp, 'suffix' => $suffix];
}

/**
 * Values for admin inputs when DB split columns are empty.
 *
 * @return array{prefix:string,amount:string,suffix:string}
 */
function btb_room_price_parts_for_admin(array $content, $slug) {
    $mapAll = btb_room_price_column_map();
    if (!isset($mapAll[$slug])) {
        return ['prefix' => '', 'amount' => '', 'suffix' => ''];
    }
    $m = $mapAll[$slug];
    $p = btb_room_price_input_plain($content[$m['prefix']] ?? '');
    $a = btb_room_price_input_plain($content[$m['amount']] ?? '');
    $s = btb_room_price_input_plain($content[$m['suffix']] ?? '');
    if ($p !== '' || $a !== '' || $s !== '') {
        return ['prefix' => $p, 'amount' => $a, 'suffix' => $s];
    }
    $legacy = trim((string) ($content[$m['legacy']] ?? ''));
    // Do not return btb_room_price_default_triplet() here: empty DB must stay empty in the admin API.
    // Otherwise get_content fills inputs with 140/130/… and auto-save persists those as real values.
    if ($legacy === '') {
        return ['prefix' => '', 'amount' => '', 'suffix' => ''];
    }
    $inf = btb_room_price_infer_from_legacy($legacy, $slug);
    if ($inf['amount'] !== '') {
        return $inf;
    }
    return ['prefix' => '', 'amount' => '', 'suffix' => ''];
}

/**
 * Composed HTML line: prefix in &lt;strong&gt; (plain text in DB), amount and suffix escaped plain text.
 */
function btb_room_price_line_from_parts_string($prefix, $amount, $suffix) {
    $p = btb_room_price_input_plain($prefix);
    $a = btb_room_price_input_plain($amount);
    $s = btb_room_price_input_plain($suffix);
    $parts = [];
    if ($p !== '') {
        $parts[] = '<strong>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</strong>';
    }
    if ($a !== '') {
        $parts[] = htmlspecialchars($a, ENT_QUOTES, 'UTF-8');
    }
    if ($s !== '') {
        $parts[] = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
    return trim(implode(' ', $parts));
}

/**
 * Full price line: split fields, else legacy HTML column, else optional fallback (use '' or stored_only helper for admin-only display).
 */
function btb_room_price_line_html(array $content, $slug, $fallbackHtml) {
    $mapAll = btb_room_price_column_map();
    if (!isset($mapAll[$slug])) {
        return $fallbackHtml;
    }
    $m = $mapAll[$slug];
    $p = trim((string) ($content[$m['prefix']] ?? ''));
    $a = trim((string) ($content[$m['amount']] ?? ''));
    $s = trim((string) ($content[$m['suffix']] ?? ''));
    if ($p !== '' || $a !== '' || $s !== '') {
        $line = btb_room_price_line_from_parts_string($p, $a, $s);
        if ($line !== '') {
            return $line;
        }
    }
    $legacy = trim((string) ($content[$m['legacy']] ?? ''));
    if ($legacy !== '') {
        return strip_tags($legacy, btb_room_price_allowed_tags());
    }
    return $fallbackHtml;
}

/**
 * Composed line from DB only (split or legacy). Empty string if nothing stored — used by get_content for admin
 * so we do not inject site default nightly rates into fields or previews.
 */
function btb_room_price_line_html_stored_only(array $content, $slug) {
    return btb_room_price_line_html($content, $slug, '');
}

/**
 * Nightly rate for cart: amount field first, then legacy line.
 *
 * @return float|null
 */
function btb_room_price_nightly_amount(array $content, $slug) {
    $mapAll = btb_room_price_column_map();
    if (!isset($mapAll[$slug])) {
        return null;
    }
    $m = $mapAll[$slug];
    $rawAmt = btb_room_price_input_plain($content[$m['amount']] ?? '');
    $n = btb_extract_first_price_number($rawAmt);
    if ($n !== null && $n > 0) {
        return $n;
    }
    $legacy = $content[$m['legacy']] ?? '';
    $n2 = btb_extract_room_nightly_rate_number($legacy);
    if ($n2 !== null && $n2 > 0) {
        return $n2;
    }
    return null;
}

/**
 * When admin POSTs split fields, keep legacy room_*_price column in sync (same HTML as public line).
 *
 * @param array<string, mixed> $post
 */
function btb_sync_room_price_legacy_fields_from_post(array &$post) {
    foreach (btb_room_price_column_map() as $slug => $m) {
        $has = isset($post[$m['prefix']]) || isset($post[$m['amount']]) || isset($post[$m['suffix']]);
        if (!$has) {
            continue;
        }
        $p = array_key_exists($m['prefix'], $post) ? (string) $post[$m['prefix']] : '';
        $a = array_key_exists($m['amount'], $post) ? (string) $post[$m['amount']] : '';
        $s = array_key_exists($m['suffix'], $post) ? (string) $post[$m['suffix']] : '';
        $post[$m['legacy']] = btb_room_price_line_from_parts_string($p, $a, $s);
    }
}

/**
 * Max bytes for one admin image upload (before server-side resize). Ensure PHP upload_max_filesize and post_max_size are not lower.
 */
function btb_upload_image_max_file_bytes() {
    return 32 * 1024 * 1024;
}

/**
 * Max long edge (px) for admin uploads by image_type slug (hero banners vs cards vs floorplans).
 * Gallery slugs get a high cap: the same file URL is used for grid thumbs and full-size/lightbox views.
 */
function btb_upload_image_max_long_edge($imageType) {
    $t = strtolower((string) $imageType);
    if (strpos($t, 'hero') !== false) {
        return 2400;
    }
    if (strpos($t, 'banner') !== false) {
        return 2200;
    }
    if (strpos($t, 'gallery') !== false) {
        return 3840;
    }
    // wellness-massage / wellness-yoga contain "massage" but are section promos, not massage.php thumbnails
    if (strpos($t, 'wellness-') === 0) {
        return 1920;
    }
    // Homepage room cards (Loki Suite, etc.) — larger promos than massage thumbnails; must run before generic "card"
    if (preg_match('/^room-[a-z0-9-]+-card$/', $t)) {
        return 1920;
    }
    // Massage service photos (Reiki, Relaxing, … on massage / wellness CMS) — full card images, not icon thumbs
    if (preg_match('/^massage-(relaxing|deep-tissue|reiki|sauna)$/', $t)) {
        return 1920;
    }
    if (strpos($t, 'card') !== false || strpos($t, 'massage') !== false) {
        return 1400;
    }
    if (in_array($t, ['basement', 'ground', 'loft'], true)) {
        return 1920;
    }
    return 1920;
}

/**
 * Resize (cap long edge) and recompress. JPEG stays JPEG. PNG is flattened onto white and saved as JPEG
 * (path becomes *.jpg; original *.png removed). GIF is unchanged (animation preserved).
 * No-op if GD is missing. Returns false only if processing failed badly (caller keeps file on disk).
 *
 * @param string $filepath In/out path under web root (e.g. assets/foo.jpg); may change to .jpg after PNG upload
 * @param int $maxLongEdge Max width or height in pixels
 * @param int $jpegQuality 60–95
 */
function btb_optimize_uploaded_image_file(&$filepath, $maxLongEdge, $jpegQuality = 84) {
    if (!extension_loaded('gd')) {
        return true;
    }
    if ($maxLongEdge < 200) {
        $maxLongEdge = 1920;
    }
    $jpegQuality = max(60, min(95, (int) $jpegQuality));
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    if ($ext === 'gif') {
        return true;
    }
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return true;
    }
    $img = false;
    if (function_exists('imagecreatefromstring')) {
        $bin = @file_get_contents($filepath);
        if ($bin !== false && $bin !== '') {
            $img = @imagecreatefromstring($bin);
        }
    }
    if ($img === false) {
        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($filepath);
        } elseif (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($filepath);
        }
    }
    if (!$img) {
        return false;
    }
    $w = (int) imagesx($img);
    $h = (int) imagesy($img);
    if ($w < 1 || $h < 1) {
        imagedestroy($img);
        return false;
    }
    $long = max($w, $h);
    $scale = $long > $maxLongEdge ? ($maxLongEdge / $long) : 1.0;
    if ($scale < 1.0 - 1e-9) {
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($img);
            return false;
        }
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
            imagealphablending($dst, true);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    if ($ext === 'png') {
        $wFlat = (int) imagesx($img);
        $hFlat = (int) imagesy($img);
        if ($wFlat < 1 || $hFlat < 1) {
            imagedestroy($img);
            return false;
        }
        $flat = imagecreatetruecolor($wFlat, $hFlat);
        if ($flat === false) {
            imagedestroy($img);
            return false;
        }
        $bg = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $bg);
        imagealphablending($flat, true);
        imagealphablending($img, true);
        imagecopy($flat, $img, 0, 0, 0, 0, $wFlat, $hFlat);
        imagedestroy($img);
        $img = $flat;

        $dir = dirname($filepath);
        $base = pathinfo($filepath, PATHINFO_FILENAME);
        $jpgPath = ($dir === '.' || $dir === '') ? ($base . '.jpg') : ($dir . '/' . $base . '.jpg');
        $ok = imagejpeg($img, $jpgPath, $jpegQuality);
        imagedestroy($img);
        if (!$ok) {
            if (is_file($jpgPath)) {
                @unlink($jpgPath);
            }
            return false;
        }
        if (is_file($filepath) && $filepath !== $jpgPath && realpath($filepath) !== realpath($jpgPath)) {
            @unlink($filepath);
        }
        $filepath = $jpgPath;
        return true;
    }

    $ok = imagejpeg($img, $filepath, $jpegQuality);
    imagedestroy($img);
    return (bool) $ok;
}

/**
 * @param string $filepath In/out; may become .jpg when a PNG was converted
 * @param string $imageType POST image_type slug
 */
function btb_optimize_uploaded_image(&$filepath, $imageType) {
    return btb_optimize_uploaded_image_file($filepath, btb_upload_image_max_long_edge($imageType), 84);
}

/**
 * Typical soft max file size (bytes) after resize — above this, admin UI suggests re-upload for this slot type.
 */
function btb_upload_image_soft_max_bytes($imageType) {
    $t = strtolower((string) $imageType);
    if (strpos($t, 'gallery') !== false) {
        return 4 * 1024 * 1024;
    }
    // Hero/banner: long edge is capped (e.g. 2200px) but PNG stays PNG — 2200px photos often exceed 3MB; JPEG usually does not.
    if (strpos($t, 'hero') !== false || strpos($t, 'banner') !== false) {
        return 8 * 1024 * 1024;
    }
    if (strpos($t, 'wellness-') === 0) {
        return (int) (2.5 * 1024 * 1024);
    }
    // Homepage room card images — same rationale as wellness-* (avoid false "Large file" after 1400px JPEG)
    if (preg_match('/^room-[a-z0-9-]+-card$/', $t)) {
        return (int) (2.5 * 1024 * 1024);
    }
    if (preg_match('/^massage-(relaxing|deep-tissue|reiki|sauna)$/', $t)) {
        return (int) (2.5 * 1024 * 1024);
    }
    if (strpos($t, 'card') !== false || strpos($t, 'massage') !== false) {
        return 850 * 1024;
    }
    return 2200 * 1024;
}

/**
 * Sanitize relative path to a file under assets/ (admin audit / tooling).
 */
function btb_admin_sanitize_asset_relative_path($relativePath) {
    $p = str_replace('\\', '/', trim((string) $relativePath));
    if ($p === '') {
        return '';
    }
    if (strpos($p, '..') !== false) {
        return '';
    }
    $p = preg_replace('#^(\./)+#', '', $p);
    $p = ltrim($p, '/');
    if (strpos($p, 'assets/') !== 0) {
        return '';
    }
    if (strlen($p) > 512) {
        return '';
    }
    if (!preg_match('#^assets/[^\s]+$#u', $p)) {
        return '';
    }
    return $p;
}

/**
 * Stream a downscaled JPEG for admin preview thumbnails (reduces bandwidth vs full-size assets/).
 * Uses GD when available; otherwise serves the original bytes with its mime type.
 *
 * @param string $relativePath Sanitized assets/… path
 * @param int $maxLongEdge Long edge cap (clamped 160–960)
 * @return bool True if a response body was sent
 */
function btb_admin_stream_jpeg_preview_thumb($relativePath, $maxLongEdge) {
    $rel = btb_admin_sanitize_asset_relative_path($relativePath);
    if ($rel === '') {
        return false;
    }
    $root = realpath(__DIR__);
    if ($root === false) {
        return false;
    }
    $full = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($full === false || !is_file($full)) {
        return false;
    }
    $rootCanon = $root . DIRECTORY_SEPARATOR;
    if (strpos($full, $rootCanon) !== 0 && $full !== $root) {
        return false;
    }
    $maxLongEdge = max(160, min(960, (int) $maxLongEdge));
    $mtime = @filemtime($full);
    $mtime = $mtime !== false ? (int) $mtime : 0;
    $etag = '"' . md5($rel . '|' . $mtime . '|' . $maxLongEdge) . '"';
    if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('HTTP/1.1 304 Not Modified');
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=86400');
        return true;
    }
    if (!extension_loaded('gd')) {
        $mime = @mime_content_type($full);
        if (!is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        header('ETag: ' . $etag);
        readfile($full);
        return true;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $img = false;
    if (function_exists('imagecreatefromstring')) {
        $bin = @file_get_contents($full);
        if ($bin !== false && $bin !== '') {
            $img = @imagecreatefromstring($bin);
        }
    }
    if ($img === false) {
        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($full);
        } elseif (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($full);
        } elseif ($ext === 'gif' && function_exists('imagecreatefromgif')) {
            $img = @imagecreatefromgif($full);
        }
    }
    if (!$img) {
        return false;
    }
    $w = (int) imagesx($img);
    $h = (int) imagesy($img);
    if ($w < 1 || $h < 1) {
        imagedestroy($img);
        return false;
    }
    $long = max($w, $h);
    $scale = $long > $maxLongEdge ? ($maxLongEdge / $long) : 1.0;
    if ($scale < 1.0 - 1e-9) {
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($img);
            return false;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    header('ETag: ' . $etag);
    imagejpeg($img, null, 82);
    imagedestroy($img);
    return true;
}

/**
 * Inspect an on-disk image vs upload-policy limits (for admin "re-upload recommended" badges).
 *
 * @return array<string, mixed>
 */
function btb_admin_audit_image_asset($relativePath, $imageType) {
    $defaults = [
        'ok' => false,
        'heavy' => false,
        'exceedsResolution' => false,
        'exceedsFileSize' => false,
        'width' => null,
        'height' => null,
        'longEdge' => null,
        'bytes' => null,
        'maxLongEdge' => null,
        'softMaxBytes' => null,
        'summary' => '',
        'error' => null,
    ];
    $rel = btb_admin_sanitize_asset_relative_path($relativePath);
    if ($rel === '') {
        return array_merge($defaults, ['error' => 'invalid_path']);
    }
    $root = realpath(__DIR__);
    if ($root === false) {
        return array_merge($defaults, ['error' => 'server']);
    }
    $full = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($full === false || !is_file($full)) {
        return array_merge($defaults, ['ok' => true, 'heavy' => false, 'error' => 'not_found']);
    }
    $rootCanon = $root . DIRECTORY_SEPARATOR;
    if (strpos($full, $rootCanon) !== 0 && $full !== $root) {
        return array_merge($defaults, ['error' => 'invalid_path']);
    }
    $bytes = @filesize($full);
    if ($bytes === false) {
        return array_merge($defaults, ['error' => 'stat']);
    }
    $bytes = (int) $bytes;
    $info = @getimagesize($full);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return array_merge($defaults, ['ok' => true, 'bytes' => $bytes, 'error' => 'not_image']);
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    $longEdge = max($w, $h);
    $maxLe = btb_upload_image_max_long_edge($imageType);
    $softB = btb_upload_image_soft_max_bytes($imageType);
    $exceedsRes = $longEdge > ($maxLe + 12);
    $exceedsBytes = $bytes > $softB;
    $heavy = $exceedsRes || $exceedsBytes;
    $parts = [];
    if ($exceedsRes) {
        $parts[] = "Image is {$w}×{$h}px; long edge exceeds ~{$maxLe}px for this slot — re-upload to optimize.";
    }
    if ($exceedsBytes) {
        $mb = round($bytes / 1048576, 2);
        $capMb = round($softB / 1048576, 1);
        $parts[] = "File size is {$mb} MB; typical optimized size for this slot is under ~{$capMb} MB — re-upload to shrink.";
    }
    if (!$heavy) {
        $parts[] = 'Within recommended limits for this slot.';
    }
    return [
        'ok' => true,
        'heavy' => $heavy,
        'exceedsResolution' => $exceedsRes,
        'exceedsFileSize' => $exceedsBytes,
        'width' => $w,
        'height' => $h,
        'longEdge' => $longEdge,
        'bytes' => $bytes,
        'maxLongEdge' => $maxLe,
        'softMaxBytes' => $softB,
        'summary' => implode(' ', $parts),
        'error' => null,
    ];
}

// Initialize common functionality
function initCommon() {
    // Set timezone
    date_default_timezone_set('America/Vancouver');
    
    // Start session if not already started (skip CLI: migration scripts include this file)
    if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
        session_start();
    }
    
    // Log initialization
    logActivity('Common utilities initialized');
}

// Auto-initialize
initCommon();
