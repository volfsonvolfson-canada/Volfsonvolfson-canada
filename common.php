<?php
// Common PHP utilities for Back to Base

// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

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

function sendError($error, $data = null) {
    sendResponse(false, $data, $error);
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
    return preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $phone);
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
        
        // Validate file size (5MB max)
        if ($fileSize > 5 * 1024 * 1024) {
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

/** Safe single-line / attribute text */
function safeOutput($value, $fallback = '') {
    return htmlspecialchars($value ?? $fallback, ENT_QUOTES, 'UTF-8');
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
    $n = btb_extract_first_price_number($legacy);
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
        return btb_room_price_default_triplet('basement');
    }
    $m = $mapAll[$slug];
    $p = btb_room_price_input_plain($content[$m['prefix']] ?? '');
    $a = btb_room_price_input_plain($content[$m['amount']] ?? '');
    $s = btb_room_price_input_plain($content[$m['suffix']] ?? '');
    if ($p !== '' || $a !== '' || $s !== '') {
        return ['prefix' => $p, 'amount' => $a, 'suffix' => $s];
    }
    $legacy = trim((string) ($content[$m['legacy']] ?? ''));
    if ($legacy === '') {
        return btb_room_price_default_triplet($slug);
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
 * Full price line for room pages and homepage cards: split fields, else legacy HTML column, else fallback.
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
    $n2 = btb_extract_first_price_number($legacy);
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

// Initialize common functionality
function initCommon() {
    // Set timezone
    date_default_timezone_set('America/Vancouver');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Log initialization
    logActivity('Common utilities initialized');
}

// Auto-initialize
initCommon();
?>
