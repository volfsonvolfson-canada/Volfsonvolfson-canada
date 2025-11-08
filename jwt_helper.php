<?php
// JWT Helper для создания и проверки JWT токенов

// Секретный ключ для подписи JWT (должен быть в config.php)
// В продакшене используйте сильный случайный ключ
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-secret-key-change-this-in-production-' . date('Y'));
}

if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}

if (!defined('JWT_EXPIRATION')) {
    define('JWT_EXPIRATION', 86400 * 30); // 30 дней
}

/**
 * Создание JWT токена
 * @param array $payload Данные для токена (user_id, email и т.д.)
 * @param int $expiration Время жизни токена в секундах
 * @return string JWT токен
 */
function createJWT($payload, $expiration = null) {
    if ($expiration === null) {
        $expiration = JWT_EXPIRATION;
    }
    
    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM
    ];
    
    $payload['iat'] = time(); // Issued at
    $payload['exp'] = time() + $expiration; // Expiration
    
    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Проверка и декодирование JWT токена
 * @param string $token JWT токен
 * @return array|false Декодированные данные или false при ошибке
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
    
    // Проверяем подпись
    $signature = base64UrlDecode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }
    
    // Декодируем payload
    $payload = json_decode(base64UrlDecode($base64UrlPayload), true);
    
    if (!$payload) {
        return false;
    }
    
    // Проверяем срок действия
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Получение токена из заголовка Authorization
 * @return string|false Токен или false
 */
function getBearerToken() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        return false;
    }
    
    $authHeader = $headers['Authorization'];
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Проверка аутентификации пользователя
 * @param mysqli $conn Соединение с БД
 * @return array|false Данные пользователя или false
 */
function authenticateUser($conn) {
    $token = getBearerToken();
    
    if (!$token) {
        // Пробуем получить из cookie
        $token = $_COOKIE['btb_auth_token'] ?? null;
    }
    
    if (!$token) {
        return false;
    }
    
    $payload = verifyJWT($token);
    
    if (!$payload || !isset($payload['user_id'])) {
        return false;
    }
    
    // Получаем пользователя из БД
    $user = fetchOne($conn, "SELECT id, email, name, phone, phone2, is_verified, created_at, last_session FROM users WHERE id = ?", [$payload['user_id']]);
    
    if (!$user) {
        return false;
    }
    
    return $user;
}

/**
 * Base64 URL encoding
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decoding
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

