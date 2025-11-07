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
